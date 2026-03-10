(function (wp, settings) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, Button, Notice, Spinner, TextControl, TextareaControl, SelectControl, CheckboxControl } = wp.components;
    const { Fragment, useState } = wp.element;
    const { useSelect, useDispatch } = wp.data;
    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    const metaKeys = settings.metaKeys || {};
    const articleModes = [
        { label: __('News', 'queerdispatch-ai-seo'), value: 'news' },
        { label: __('Editorial', 'queerdispatch-ai-seo'), value: 'editorial' },
        { label: __('Explainer', 'queerdispatch-ai-seo'), value: 'explainer' },
        { label: __('Social Copy', 'queerdispatch-ai-seo'), value: 'social_copy' },
    ];
    const beatPresets = Object.entries(settings.beatPresets || {}).map(([value, label]) => ({ value, label }));
    const editorialStatuses = Object.entries(settings.editorialStatuses || {}).map(([value, label]) => ({ value, label }));

    const stringifyLines = (items, mapFn) => (Array.isArray(items) ? items.map(mapFn).join('\n') : '');
    const prettySocial = (items) => stringifyLines(items, (item) => `[${item.label || item.network}]\n${item.body}\n`);

    const Sidebar = function () {
        const { meta = {}, postId, postType, postTitle, excerpt, content } = useSelect((select) => {
            const editor = select('core/editor');
            const current = editor.getCurrentPost();
            return {
                meta: editor.getEditedPostAttribute('meta') || {},
                postId: current && current.id,
                postType: current && current.type,
                postTitle: editor.getEditedPostAttribute('title') || '',
                excerpt: editor.getEditedPostAttribute('excerpt') || '',
                content: editor.getEditedPostContent() || '',
            };
        }, []);

        const { editPost } = useDispatch('core/editor');
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        const [result, setResult] = useState(null);
        const [articleMode, setArticleMode] = useState(meta[metaKeys.article_mode] || 'news');
        const [beatPreset, setBeatPreset] = useState(meta[metaKeys.beat_preset] || 'general');
        const [editorialStatus, setEditorialStatus] = useState(meta[metaKeys.editorial_status] || 'drafted');
        const [selectedFields, setSelectedFields] = useState({
            focusKeyphrase: true,
            seoTitle: true,
            metaDescription: true,
            socialFields: true,
            excerpt: true,
            disclosure: true,
            notes: true,
            internalLinks: true,
            headlineVariants: true,
            socialPosts: true,
            infographicPrompt: true,
            imageMetadata: true,
            storyPackage: true,
        });

        if (!postId || !settings.postTypes.includes(postType)) {
            return wp.element.createElement(
                Fragment,
                null,
                wp.element.createElement(PluginSidebarMoreMenuItem, { target: 'qd-ai-seo-sidebar' }, settings.strings.title),
                wp.element.createElement(
                    PluginSidebar,
                    { name: 'qd-ai-seo-sidebar', title: settings.strings.title },
                    wp.element.createElement(Notice, { status: 'warning', isDismissible: false }, settings.strings.selectType)
                )
            );
        }

        const toggleSelection = (key, value) => setSelectedFields((prev) => ({ ...prev, [key]: !!value }));

        const applyToMeta = () => {
            if (!result) {
                return;
            }
            const nextMeta = { ...meta };
            nextMeta[metaKeys.article_mode] = articleMode;
            nextMeta[metaKeys.beat_preset] = beatPreset;
            nextMeta[metaKeys.editorial_status] = editorialStatus;

            if (selectedFields.focusKeyphrase) {
                nextMeta[metaKeys.focus_keyphrase] = result.focus_keyphrase || '';
                nextMeta[metaKeys.keyphrase_variants] = result.keyphrase_variants || [];
            }
            if (selectedFields.seoTitle) nextMeta[metaKeys.seo_title] = result.seo_title || '';
            if (selectedFields.metaDescription) nextMeta[metaKeys.meta_description] = result.meta_description || '';
            if (selectedFields.socialFields) {
                nextMeta[metaKeys.social_title] = result.social_title || '';
                nextMeta[metaKeys.social_description] = result.social_description || '';
            }
            if (selectedFields.excerpt) nextMeta[metaKeys.excerpt_suggestion] = result.excerpt_suggestion || '';
            if (selectedFields.disclosure) nextMeta[metaKeys.ai_disclosure] = result.ai_disclosure || '';
            if (selectedFields.notes) nextMeta[metaKeys.analysis_notes] = result.analysis_notes || '';
            if (selectedFields.internalLinks) nextMeta[metaKeys.internal_link_suggestions] = result.internal_link_suggestions || [];
            if (selectedFields.headlineVariants) nextMeta[metaKeys.headline_variants] = result.headline_variants || [];
            if (selectedFields.socialPosts) nextMeta[metaKeys.social_posts] = result.social_posts || [];
            if (selectedFields.infographicPrompt) nextMeta[metaKeys.infographic_prompt] = result.infographic_prompt || '';
            if (selectedFields.imageMetadata) {
                nextMeta[metaKeys.featured_image_alt_suggestion] = result.featured_image_alt_suggestion || '';
                nextMeta[metaKeys.featured_image_caption_suggestion] = result.featured_image_caption_suggestion || '';
            }
            if (selectedFields.storyPackage) nextMeta[metaKeys.story_package] = result.story_package || '';

            const update = { meta: nextMeta };
            if (selectedFields.excerpt && !excerpt && result.excerpt_suggestion && settings.featureFlags.autoExcerpt) {
                update.excerpt = result.excerpt_suggestion;
            }
            editPost(update);
            setNotice(settings.strings.saved || __('Saved generated fields into post meta.', 'queerdispatch-ai-seo'));
        };

        const saveWorkflowOnly = () => {
            const nextMeta = { ...meta };
            nextMeta[metaKeys.article_mode] = articleMode;
            nextMeta[metaKeys.beat_preset] = beatPreset;
            nextMeta[metaKeys.editorial_status] = editorialStatus;
            editPost({ meta: nextMeta });
            setNotice(__('Saved workflow fields.', 'queerdispatch-ai-seo'));
        };

        const runGenerate = async () => {
            setLoading(true);
            setError('');
            setNotice('');
            try {
                const response = await apiFetch({
                    path: 'qd-ai-seo/v1/generate',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': settings.nonce },
                    data: {
                        post_id: postId,
                        content,
                        article_mode: articleMode,
                        beat_preset: beatPreset,
                    },
                });
                setResult(response.data || null);
            } catch (err) {
                setError(err && err.message ? err.message : settings.strings.error);
            } finally {
                setLoading(false);
            }
        };

        return wp.element.createElement(
            Fragment,
            null,
            wp.element.createElement(PluginSidebarMoreMenuItem, { target: 'qd-ai-seo-sidebar' }, settings.strings.title),
            wp.element.createElement(
                PluginSidebar,
                { name: 'qd-ai-seo-sidebar', title: settings.strings.title },
                error ? wp.element.createElement(Notice, { status: 'error', isDismissible: true, onRemove: () => setError('') }, error) : null,
                notice ? wp.element.createElement(Notice, { status: 'success', isDismissible: true, onRemove: () => setNotice('') }, notice) : null,
                wp.element.createElement(
                    PanelBody,
                    { title: __('Actions', 'queerdispatch-ai-seo'), initialOpen: true },
                    wp.element.createElement('p', null, __('Generate a full SEO, social, workflow, and visual package for this draft. Review everything before publishing.', 'queerdispatch-ai-seo')),
                    wp.element.createElement(SelectControl, { label: __('Article mode', 'queerdispatch-ai-seo'), value: articleMode, options: articleModes, onChange: setArticleMode }),
                    wp.element.createElement(SelectControl, { label: __('Beat preset', 'queerdispatch-ai-seo'), value: beatPreset, options: beatPresets, onChange: setBeatPreset }),
                    wp.element.createElement(SelectControl, { label: __('Editorial status', 'queerdispatch-ai-seo'), value: editorialStatus, options: editorialStatuses, onChange: setEditorialStatus }),
                    wp.element.createElement(Button, { variant: 'primary', onClick: runGenerate, disabled: loading || !postId }, loading ? settings.strings.working : settings.strings.generate),
                    ' ',
                    wp.element.createElement(Button, { variant: 'secondary', onClick: applyToMeta, disabled: !result || loading }, settings.strings.save),
                    ' ',
                    wp.element.createElement(Button, { variant: 'tertiary', onClick: saveWorkflowOnly, disabled: loading }, __('Save workflow only', 'queerdispatch-ai-seo')),
                    loading ? wp.element.createElement('div', { style: { marginTop: '12px' } }, wp.element.createElement(Spinner, null)) : null
                ),
                wp.element.createElement(
                    PanelBody,
                    { title: __('Apply selections', 'queerdispatch-ai-seo'), initialOpen: false },
                    wp.element.createElement(CheckboxControl, { label: __('Focus keyphrase + variants', 'queerdispatch-ai-seo'), checked: selectedFields.focusKeyphrase, onChange: (v) => toggleSelection('focusKeyphrase', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('SEO title', 'queerdispatch-ai-seo'), checked: selectedFields.seoTitle, onChange: (v) => toggleSelection('seoTitle', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Meta description', 'queerdispatch-ai-seo'), checked: selectedFields.metaDescription, onChange: (v) => toggleSelection('metaDescription', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Social title + description', 'queerdispatch-ai-seo'), checked: selectedFields.socialFields, onChange: (v) => toggleSelection('socialFields', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Suggested excerpt', 'queerdispatch-ai-seo'), checked: selectedFields.excerpt, onChange: (v) => toggleSelection('excerpt', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('AI disclosure', 'queerdispatch-ai-seo'), checked: selectedFields.disclosure, onChange: (v) => toggleSelection('disclosure', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Analysis notes', 'queerdispatch-ai-seo'), checked: selectedFields.notes, onChange: (v) => toggleSelection('notes', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Internal link suggestions', 'queerdispatch-ai-seo'), checked: selectedFields.internalLinks, onChange: (v) => toggleSelection('internalLinks', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Headline variants', 'queerdispatch-ai-seo'), checked: selectedFields.headlineVariants, onChange: (v) => toggleSelection('headlineVariants', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Social posts', 'queerdispatch-ai-seo'), checked: selectedFields.socialPosts, onChange: (v) => toggleSelection('socialPosts', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Infographic prompt', 'queerdispatch-ai-seo'), checked: selectedFields.infographicPrompt, onChange: (v) => toggleSelection('infographicPrompt', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Image alt + caption suggestions', 'queerdispatch-ai-seo'), checked: selectedFields.imageMetadata, onChange: (v) => toggleSelection('imageMetadata', v) }),
                    wp.element.createElement(CheckboxControl, { label: __('Story package export box', 'queerdispatch-ai-seo'), checked: selectedFields.storyPackage, onChange: (v) => toggleSelection('storyPackage', v) })
                ),
                wp.element.createElement(
                    PanelBody,
                    { title: __('Current draft context', 'queerdispatch-ai-seo'), initialOpen: false },
                    wp.element.createElement(TextControl, { label: __('Title', 'queerdispatch-ai-seo'), value: postTitle, readOnly: true }),
                    wp.element.createElement(TextareaControl, { label: __('Excerpt', 'queerdispatch-ai-seo'), value: excerpt, readOnly: true })
                ),
                result ? wp.element.createElement(
                    Fragment,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Generated SEO', 'queerdispatch-ai-seo'), initialOpen: true },
                        wp.element.createElement(TextControl, { label: __('Focus keyphrase', 'queerdispatch-ai-seo'), value: result.focus_keyphrase || '', readOnly: true }),
                        wp.element.createElement(TextControl, { label: __('SEO title', 'queerdispatch-ai-seo'), value: result.seo_title || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Meta description', 'queerdispatch-ai-seo'), value: result.meta_description || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Social title', 'queerdispatch-ai-seo'), value: result.social_title || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Social description', 'queerdispatch-ai-seo'), value: result.social_description || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Keyphrase variants', 'queerdispatch-ai-seo'), value: stringifyLines(result.keyphrase_variants, (item) => '- ' + item), readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Headline variants', 'queerdispatch-ai-seo'), value: stringifyLines(result.headline_variants, (item) => '- ' + item), readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Suggested excerpt', 'queerdispatch-ai-seo'), value: result.excerpt_suggestion || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('AI disclosure', 'queerdispatch-ai-seo'), value: result.ai_disclosure || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Analysis notes', 'queerdispatch-ai-seo'), value: result.analysis_notes || '', readOnly: true })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Social promotion copy', 'queerdispatch-ai-seo'), initialOpen: false },
                        wp.element.createElement(TextareaControl, { label: __('Network-ready posts', 'queerdispatch-ai-seo'), value: prettySocial(result.social_posts), readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Infographic / featured image prompt', 'queerdispatch-ai-seo'), value: result.infographic_prompt || '', readOnly: true })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Featured image metadata', 'queerdispatch-ai-seo'), initialOpen: false },
                        wp.element.createElement(TextareaControl, { label: __('Alt text suggestion', 'queerdispatch-ai-seo'), value: result.featured_image_alt_suggestion || '', readOnly: true }),
                        wp.element.createElement(TextareaControl, { label: __('Caption suggestion', 'queerdispatch-ai-seo'), value: result.featured_image_caption_suggestion || '', readOnly: true })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Story package export', 'queerdispatch-ai-seo'), initialOpen: false },
                        wp.element.createElement(TextareaControl, { label: __('Copy-ready package', 'queerdispatch-ai-seo'), value: result.story_package || '', readOnly: true })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Internal link suggestions', 'queerdispatch-ai-seo'), initialOpen: false },
                        wp.element.createElement(TextareaControl, { label: __('Suggestions', 'queerdispatch-ai-seo'), value: stringifyLines(result.internal_link_suggestions, (item) => `${item.anchor} → ${item.title} (${item.url}) — ${item.reason}`), readOnly: true })
                    )
                ) : null
            )
        );
    };

    registerPlugin('qd-ai-seo-plugin', {
        render: Sidebar,
        icon: 'search',
    });
})(window.wp, window.qdAiSeo || {});
