/**
 * Warning triangle for a menu item the shop's plan doesn't include (and it
 * hasn't bought as an add-on). Hover or keyboard focus shows the tooltip.
 */
export default function RequiresSubscription() {
    return (
        <span
            className="group/warn relative ms-1.5 inline-flex items-center"
            tabIndex={0}
            aria-label="Requires a subscription"
        >
            <svg
                className="h-4 w-4 text-amber-500"
                viewBox="0 0 20 20"
                fill="currentColor"
                aria-hidden="true"
            >
                <path
                    fillRule="evenodd"
                    d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z"
                    clipRule="evenodd"
                />
            </svg>
            <span
                role="tooltip"
                className="pointer-events-none invisible absolute left-1/2 top-full z-50 mt-1 -translate-x-1/2 whitespace-nowrap rounded bg-gray-800 px-2 py-1 text-xs font-normal normal-case text-white opacity-0 shadow transition group-hover/warn:visible group-hover/warn:opacity-100 group-focus/warn:visible group-focus/warn:opacity-100"
            >
                Requires a subscription
            </span>
        </span>
    );
}
