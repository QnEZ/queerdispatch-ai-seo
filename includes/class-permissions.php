<?php

declare(strict_types=1);

namespace QueerDispatch\AISEO;

if (! defined('ABSPATH')) {
    exit;
}

final class Permissions
{
    public static function current_user_can_generate(?int $post_id = null): bool
    {
        if (! is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();
        if (! $user || 0 === (int) $user->ID) {
            return false;
        }

        $allowed_roles = Settings::get_allowed_roles();
        if ([] !== $allowed_roles) {
            $has_allowed_role = false;
            foreach ((array) $user->roles as $role) {
                if (in_array((string) $role, $allowed_roles, true)) {
                    $has_allowed_role = true;
                    break;
                }
            }

            if (! $has_allowed_role && ! user_can($user, 'manage_options')) {
                return false;
            }
        }

        if (null !== $post_id) {
            return current_user_can('edit_post', $post_id);
        }

        return current_user_can('edit_posts');
    }

    public static function get_generation_count_for_user_today(int $user_id): int
    {
        $counts = get_option('qd_ai_seo_daily_generation_counts', []);
        if (! is_array($counts)) {
            return 0;
        }

        $today = gmdate('Y-m-d');
        return absint($counts[$today][$user_id] ?? 0);
    }

    public static function increment_generation_count_for_current_user(): void
    {
        $user_id = get_current_user_id();
        if ($user_id < 1) {
            return;
        }

        $counts = get_option('qd_ai_seo_daily_generation_counts', []);
        if (! is_array($counts)) {
            $counts = [];
        }

        $today = gmdate('Y-m-d');
        if (! isset($counts[$today]) || ! is_array($counts[$today])) {
            $counts[$today] = [];
        }

        $counts[$today][$user_id] = absint($counts[$today][$user_id] ?? 0) + 1;

        foreach (array_keys($counts) as $date_key) {
            if ($date_key < gmdate('Y-m-d', strtotime('-7 days'))) {
                unset($counts[$date_key]);
            }
        }

        update_option('qd_ai_seo_daily_generation_counts', $counts, false);
    }

    public static function user_is_over_daily_limit(): bool
    {
        $limit = absint((string) Settings::get_option('daily_generation_limit', '50'));
        if ($limit < 1 || current_user_can('manage_options')) {
            return false;
        }

        return self::get_generation_count_for_user_today(get_current_user_id()) >= $limit;
    }
}
