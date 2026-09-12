<?php

declare(strict_types=1);

namespace App\Filament;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Notifications\View\NotificationsIconAlias;
use Filament\Schemas\View\SchemaIconAlias;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\View\SupportIconAlias;
use Filament\Tables\View\TablesIconAlias;
use Filament\View\PanelsIconAlias;

/**
 * Filament's own chrome — sort arrows, chevrons, the eye on "View", the magnifier in search — is
 * Heroicons by default, resolved through named aliases. The Modernist system is set in Lucide
 * throughout, and a panel whose resources use one set while its tables and menus use another
 * reads as two products. This registers the set for every alias a person actually sees; the
 * builder / rich-editor / file-cropper internals are left alone because the product uses none.
 *
 * Icons the RESOURCES choose (navigation, actions) are set at the resource, as `LucideIcon::*`.
 */
final class LucideChrome
{
    public static function register(): void
    {
        FilamentIcon::register([
            // ---- panel shell ----
            PanelsIconAlias::GLOBAL_SEARCH_FIELD => LucideIcon::Search,
            PanelsIconAlias::PAGES_DASHBOARD_NAVIGATION_ITEM => LucideIcon::LayoutDashboard,
            PanelsIconAlias::PAGES_DASHBOARD_ACTIONS_FILTER => LucideIcon::Funnel,
            PanelsIconAlias::RESOURCES_PAGES_EDIT_RECORD_NAVIGATION_ITEM => LucideIcon::Pencil,
            PanelsIconAlias::RESOURCES_PAGES_VIEW_RECORD_NAVIGATION_ITEM => LucideIcon::Eye,
            PanelsIconAlias::RESOURCES_PAGES_MANAGE_RELATED_RECORDS_NAVIGATION_ITEM => LucideIcon::Layers,
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => LucideIcon::PanelLeftClose,
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL => LucideIcon::PanelLeftOpen,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON => LucideIcon::PanelLeftOpen,
            PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL => LucideIcon::PanelLeftClose,
            PanelsIconAlias::SIDEBAR_GROUP_COLLAPSE_BUTTON => LucideIcon::ChevronUp,
            PanelsIconAlias::SUB_NAVIGATION_MOBILE_MENU_BUTTON => LucideIcon::Menu,
            PanelsIconAlias::TOPBAR_OPEN_SIDEBAR_BUTTON => LucideIcon::Menu,
            PanelsIconAlias::TOPBAR_CLOSE_SIDEBAR_BUTTON => LucideIcon::X,
            PanelsIconAlias::TOPBAR_GROUP_TOGGLE_BUTTON => LucideIcon::ChevronDown,
            PanelsIconAlias::TOPBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON => LucideIcon::Bell,
            PanelsIconAlias::SIDEBAR_OPEN_DATABASE_NOTIFICATIONS_BUTTON => LucideIcon::Bell,
            PanelsIconAlias::THEME_SWITCHER_LIGHT_BUTTON => LucideIcon::Sun,
            PanelsIconAlias::THEME_SWITCHER_DARK_BUTTON => LucideIcon::Moon,
            PanelsIconAlias::THEME_SWITCHER_SYSTEM_BUTTON => LucideIcon::Monitor,
            PanelsIconAlias::USER_MENU_PROFILE_ITEM => LucideIcon::UserRound,
            PanelsIconAlias::USER_MENU_LOGOUT_BUTTON => LucideIcon::LogOut,
            PanelsIconAlias::USER_MENU_TOGGLE_BUTTON => LucideIcon::ChevronDown,
            PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON => LucideIcon::LogOut,

            // ---- tables ----
            TablesIconAlias::SEARCH_FIELD => LucideIcon::Search,
            TablesIconAlias::ACTIONS_FILTER => LucideIcon::Funnel,
            TablesIconAlias::ACTIONS_GROUP => LucideIcon::Layers,
            TablesIconAlias::ACTIONS_OPEN_BULK_ACTIONS => LucideIcon::EllipsisVertical,
            TablesIconAlias::ACTIONS_COLUMN_MANAGER => LucideIcon::Columns3,
            TablesIconAlias::ACTIONS_ENABLE_REORDERING => LucideIcon::GripVertical,
            TablesIconAlias::ACTIONS_DISABLE_REORDERING => LucideIcon::Check,
            TablesIconAlias::COLUMNS_COLLAPSE_BUTTON => LucideIcon::ChevronDown,
            TablesIconAlias::COLUMNS_ICON_COLUMN_TRUE => LucideIcon::CircleCheck,
            TablesIconAlias::COLUMNS_ICON_COLUMN_FALSE => LucideIcon::CircleX,
            TablesIconAlias::EMPTY_STATE => LucideIcon::Inbox,
            TablesIconAlias::FILTERS_REMOVE_ALL_BUTTON => LucideIcon::X,
            TablesIconAlias::GROUPING_COLLAPSE_BUTTON => LucideIcon::ChevronDown,
            TablesIconAlias::HEADER_CELL_SORT_BUTTON => LucideIcon::ArrowUpDown,
            TablesIconAlias::HEADER_CELL_SORT_ASC_BUTTON => LucideIcon::ArrowUp,
            TablesIconAlias::HEADER_CELL_SORT_DESC_BUTTON => LucideIcon::ArrowDown,
            TablesIconAlias::REORDER_HANDLE => LucideIcon::GripVertical,

            // ---- actions ----
            ActionsIconAlias::ACTION_GROUP => LucideIcon::EllipsisVertical,
            ActionsIconAlias::VIEW_ACTION => LucideIcon::Eye,
            ActionsIconAlias::VIEW_ACTION_GROUPED => LucideIcon::Eye,
            ActionsIconAlias::EDIT_ACTION => LucideIcon::Pencil,
            ActionsIconAlias::EDIT_ACTION_GROUPED => LucideIcon::Pencil,
            ActionsIconAlias::CREATE_ACTION_GROUPED => LucideIcon::Plus,
            ActionsIconAlias::DELETE_ACTION => LucideIcon::Trash2,
            ActionsIconAlias::DELETE_ACTION_GROUPED => LucideIcon::Trash2,
            ActionsIconAlias::DELETE_ACTION_MODAL => LucideIcon::Trash2,
            ActionsIconAlias::RESTORE_ACTION => LucideIcon::ArchiveRestore,
            ActionsIconAlias::RESTORE_ACTION_GROUPED => LucideIcon::ArchiveRestore,
            ActionsIconAlias::RESTORE_ACTION_MODAL => LucideIcon::ArchiveRestore,
            ActionsIconAlias::FORCE_DELETE_ACTION => LucideIcon::Trash2,
            ActionsIconAlias::FORCE_DELETE_ACTION_GROUPED => LucideIcon::Trash2,
            ActionsIconAlias::FORCE_DELETE_ACTION_MODAL => LucideIcon::Trash2,
            ActionsIconAlias::EXPORT_ACTION_GROUPED => LucideIcon::Download,
            ActionsIconAlias::IMPORT_ACTION_GROUPED => LucideIcon::Upload,
            ActionsIconAlias::MODAL_CONFIRMATION => LucideIcon::CircleAlert,

            // ---- support: modals, breadcrumbs, pagination, sections ----
            SupportIconAlias::MODAL_CLOSE_BUTTON => LucideIcon::X,
            SupportIconAlias::BADGE_DELETE_BUTTON => LucideIcon::X,
            SupportIconAlias::BREADCRUMBS_SEPARATOR => LucideIcon::ChevronRight,
            SupportIconAlias::BREADCRUMBS_SEPARATOR_RTL => LucideIcon::ChevronLeft,
            SupportIconAlias::PAGINATION_FIRST_BUTTON => LucideIcon::ChevronsLeft,
            SupportIconAlias::PAGINATION_FIRST_BUTTON_RTL => LucideIcon::ChevronsRight,
            SupportIconAlias::PAGINATION_LAST_BUTTON => LucideIcon::ChevronsRight,
            SupportIconAlias::PAGINATION_LAST_BUTTON_RTL => LucideIcon::ChevronsLeft,
            SupportIconAlias::PAGINATION_NEXT_BUTTON => LucideIcon::ChevronRight,
            SupportIconAlias::PAGINATION_NEXT_BUTTON_RTL => LucideIcon::ChevronLeft,
            SupportIconAlias::PAGINATION_PREVIOUS_BUTTON => LucideIcon::ChevronLeft,
            SupportIconAlias::PAGINATION_PREVIOUS_BUTTON_RTL => LucideIcon::ChevronRight,
            SupportIconAlias::SECTION_COLLAPSE_BUTTON => LucideIcon::ChevronDown,

            // ---- notifications and callouts ----
            NotificationsIconAlias::NOTIFICATION_CLOSE_BUTTON => LucideIcon::X,
            NotificationsIconAlias::NOTIFICATION_DANGER => LucideIcon::CircleX,
            NotificationsIconAlias::NOTIFICATION_INFO => LucideIcon::Info,
            NotificationsIconAlias::NOTIFICATION_SUCCESS => LucideIcon::CircleCheck,
            NotificationsIconAlias::NOTIFICATION_WARNING => LucideIcon::TriangleAlert,
            NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE => LucideIcon::Inbox,
            SchemaIconAlias::COMPONENTS_CALLOUT_DANGER => LucideIcon::CircleX,
            SchemaIconAlias::COMPONENTS_CALLOUT_INFO => LucideIcon::Info,
            SchemaIconAlias::COMPONENTS_CALLOUT_SUCCESS => LucideIcon::CircleCheck,
            SchemaIconAlias::COMPONENTS_CALLOUT_WARNING => LucideIcon::TriangleAlert,
            SchemaIconAlias::COMPONENTS_TABS_DROPDOWN_TRIGGER_BUTTON => LucideIcon::ChevronDown,
            SchemaIconAlias::COMPONENTS_TABS_MORE_TABS_BUTTON => LucideIcon::Ellipsis,
            SchemaIconAlias::COMPONENTS_WIZARD_COMPLETED_STEP => LucideIcon::Check,
        ]);
    }
}
