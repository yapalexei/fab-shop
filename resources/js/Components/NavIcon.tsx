import {
    AdjustmentsHorizontalIcon,
    BookOpenIcon,
    BuildingOffice2Icon,
    ChartBarSquareIcon,
    CheckBadgeIcon,
    ClipboardDocumentListIcon,
    Cog6ToothIcon,
    CubeIcon,
    DocumentTextIcon,
    PuzzlePieceIcon,
    QueueListIcon,
    ReceiptPercentIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    SparklesIcon,
    TagIcon,
    UserGroupIcon,
    UserIcon,
    ViewColumnsIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/react/24/outline';
import { ComponentType, SVGProps } from 'react';

// Heroicons has no drill bit or power drill, so these two are drawn to match
// its outline style (24x24 grid, 1.5 stroke, round caps and joins).
function DrillBitIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            <path d="M9.5 2.5h5V7h-5z" />
            <path d="M9.5 7v10l2.5 4.5 2.5-4.5V7" />
            <path d="m9.5 10.5 5-2M9.5 14l5-2M9.5 17.5l5-2" />
        </svg>
    );
}

function CordlessDrillIcon(props: SVGProps<SVGSVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            {...props}
        >
            <path d="M2 9h3M5 7.5h2.5v3H5z" />
            <rect x="7.5" y="6" width="12" height="6" rx="2" />
            <path d="m12.5 12-1 6h4l1-6M15 8.5h2.5" />
            <rect x="9" y="18" width="9" height="3.5" rx="1" />
        </svg>
    );
}

// Menu label -> Heroicons (outline) glyph.
const ICONS: Record<string, ComponentType<SVGProps<SVGSVGElement>>> = {
    Dashboard: Squares2X2Icon,
    Quotes: DocumentTextIcon,
    Jobs: ViewColumnsIcon,
    Reports: ChartBarSquareIcon,
    Customers: UserIcon,
    Materials: CubeIcon,
    Services: WrenchScrewdriverIcon,
    'Business partners': BuildingOffice2Icon,
    Equipment: CordlessDrillIcon,
    Tooling: DrillBitIcon,
    'Material types': TagIcon,
    Procedures: ClipboardDocumentListIcon,
    Certifications: CheckBadgeIcon,
    Markup: ReceiptPercentIcon,
    'Job board stages': QueueListIcon,
    'Shop settings': AdjustmentsHorizontalIcon,
    Team: UserGroupIcon,
    'Roles & permissions': ShieldCheckIcon,
    QuickBooks: BookOpenIcon,
    'Add-ons': PuzzlePieceIcon,
    'AI assistant': SparklesIcon,
};

/** Icon for a menu item, looked up by its label; renders nothing if unknown. */
export default function NavIcon({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    const Icon = ICONS[label];

    if (!Icon) {
        return null;
    }

    return (
        <Icon
            className={
                className ??
                'me-1.5 inline-block h-4 w-4 shrink-0 self-center align-text-bottom'
            }
            aria-hidden="true"
        />
    );
}
