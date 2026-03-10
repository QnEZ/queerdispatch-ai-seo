(function (wp, settings) {
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody, Button, TextControl, TextareaControl, Notice, Spinner, SelectControl, CheckboxControl } = wp.components;
    const { Fragment, useState, useEffect } = wp.element;
    const { useSelect, useDispatch } = wp.data;
    const apiFetch = wp.apiFetch;
    const { __ } = wp.i18n;

    const metaKeys = settings.metaKeys || {};
    const enabledTypes = settings.postTypes || ['post'];
    const articleModes = [
        { label: __('News', 'queerdispatch-ai-seo'), value: 'news' },
        { label: __('Editorial', 'queerdispatch-ai-seo'), value: 'editorial' },
        { label: __('Explainer', 'queerdispatch-ai-seo'), value: 'explainer' },
        { label: __('Social Copy', 'queerdispatch-ai-seo'), value: 'social_copy' },
    ];

    const stringifyLines = (items, mapper) => {
        if (!Array.isArray(items) || !items.length) {
            return '';
        }
        return items.map(mapper).join('\n');
    };

    const defaultSelections = {
        focusKeyphrase: true,
        seoTitle: true,
        metaDescription: true,
        socialFields: true,
        excerpt: true,
        disclosure: true,
        notes: true,
        internalLinks: true,
        headlineVariants: true,
    };

    const Sidebar = function () {
        const postType = useSelect((select) => select('core/editor').getCurrentPostType(), []);
        const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
        const postTitle = useSelect((select) => select('core/editor').getEditedPostAttribute('title') || '', []);
        const content = useSelect((select) => select('core/editor').getEditedPostContent(), []);
        const excerpt = useSelect((select) => select('core/editor').getEditedPostAttribute('excerpt') || '', []);
        const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {}, []);
        const { editPost } = useDispatch('core/editor');

        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const [notice, setNotice] = useState('');
        const [result, setResult] = useState(null);
        const [articleMode, setArticleMode] = useState(meta[metaKeys.article_mode] || 'news');
        const [selectedFields, setSelectedFields] = useState(defaultSelections);

        useEffect(() => {
            setResult(null);
            setError('');
            setNotice('');
            setArticleMode(meta[metaKeys.article_mode] || 'news');
            setSelectedFields(defaultSelections);
        }, [postId]);

        if (!enabledTypes.includes(postType)) {
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

        const toggleSelection = (key, checked) => {
            setSelectedFields(Object.assign({}, selectedFields, { [key]: checked }));
        };

        const applyToMeta = () => {
            if (!result) {
                return;
            }

            const nextMeta = Object.assign({}, meta, {
                [metaKeys.article_mode]: articleMode,
            });

            if (selectedFields.focusKeyphrase) {
                nextMeta[metaKeys.focus_keyphrase] = result.focus_keyphrase || '';
                nextMeta[metaKeys.keyphrase_variants] = result.keyphrase_variants || [];
            }
            if (selectedFields.headlineVariants) {
                nextMeta[metaKeys.headline_variants] = result.headline_variants || [];
            }
            if (selectedFields.seoTitle) {
                nextMeta[metaKeys.seo_title] = result.seo_title || '';
            }
            if (selectedFields.metaDescription) {
                nextMeta[metaKeys.meta_description] = result.meta_description || '';
            }
            if (selectedFields.socialFields) {
                nextMeta[metaKeys.social_title] = result.social_title || '';
                nextMeta[metaKeys.social_description] = result.social_description || '';
            }
            if (selectedFields.disclosure) {
                nextMeta[metaKeys.ai_disclosure] = result.ai_disclosure || '';
            }
            if (selectedFields.excerpt) {
                nextMeta[metaKeys.excerpt_suggestion] = result.excerpt_suggestion || '';
            }
            if (selectedFields.notes) {
                nextMeta[metaKeys.analysis_notes] = result.analysis_notes || '';
            }
            if (selectedFields.internalLinks) {
                nextMeta[metaKeys.internal_link_suggestions] = result.internal_link_suggestions || [];
            }

            const update = { meta: nextMeta };
            if (selectedFields.excerpt && !excerpt && result.excerpt_suggestion && settings.featureFlags.autoExcerpt) {
                update.excerpt = result.excerpt_suggestion;
            }

            editPost(update);
            setNotice(settings.strings.saved || __('Saved generated fields into post meta.', 'queerdispatch-ai-seo'));
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
                    },
                });
                setResult(response.data || null);
            } catch (err) {
                setError(err && err.message ? err.message : settings.strings.error);
            } finally {
                setLoading(false);
            }
        };

        const activeIntegrationText = [];
        if (settings.integrations && settings.integrations.yoast) {
            activeIntegrationText.push('Yoast');
        }
        if (settings.integrations && settings.integrations.rank_math) {
            activeIntegrationText.push('Rank Math');
        }

        return wp.element.createElement(
            Fragment,
            null,
            wp.element.createElement(PluginSidebarMoreMenuItem, { target: 'qd-ai-seo-sidebar' }, settings.strings.title),
            wp.element.createElement(
                PluginSidebar,
                { name: 'qd-ai-seo-sidebar', title: settings.strings.title },
                error ? wp.element.createElement(Notice, { status: 'error', isDismissible: true, onRemove: () => setError('') }, error) : null,
                notice ? wp.element.createElement(Notice, { status: 'success', isDismissible: true, onRemove: () => setNotice('') }, notice) : null,
                activeIntegrationText.length && settings.featureFlags.pluginIntegration
                    ? wp.element.createElement(Notice, { status: 'info', isDismissible: false }, __('Active SEO integration sync:', 'queerdispatch-ai-seo') + ' ' + activeIntegrationText.join(', '))
                    : null,
                wp.element.createElement(
                    PanelBody,
                    { title: __('Actions', 'queerdispatch-ai-seo'), initialOpen: true },
                    wp.element.createElement('p', null, __('Generate a full SEO package for this draft. Review everything before publishing.', 'queerdispatch-ai-seo')),
                    wp.element.createElement(SelectControl, {
                        label: __('Article mode', 'queerdispatch-ai-seo'),
                        value: articleMode,
                        options: articleModes,
                        onChange: setArticleMode,
                    }),
                    wp.element.createElement(Button, { variant: 'primary', onClick: runGenerate, disabled: loading || !postId }, loading ? settings.strings.working : settings.strings.generate),
                    ' ',
                    wp.element.createElement(Button, { variant: 'secondary', onClick: applyToMeta, disabled: !result || loading }, settings.strings.save),
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
                    wp.element.createElement(CheckboxControl, { label: __('Headline variants', 'queerdispatch-ai-seo'), checked: selectedFields.headlineVariants, onChange: (v) => toggleSelection('headlineVariants', v) })
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
                        { title: __('Internal link suggestions', 'queerdispatch-ai-seo'), initialOpen: false },
                        wp.element.createElement(TextareaControl, {
                            label: __('Suggestions', 'queerdispatch-ai-seo'),
                            value: stringifyLines(result.internal_link_suggestions, (item) => `${item.anchor} → ${item.title} (${item.url}) — ${item.reason}`),
                            readOnly: true,
                        })
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
