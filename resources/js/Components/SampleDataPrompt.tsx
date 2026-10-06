/**
 * The line in a page's help text that offers the sample-data dialog. The
 * dialog itself belongs to the page (help popovers unmount when they close),
 * so this only reports the click.
 */
export default function SampleDataPrompt({
    label,
    onOpen,
}: {
    /** Plural noun, e.g. "services". */
    label: string;
    onOpen: () => void;
}) {
    return (
        <p>
            Want a head start?{' '}
            <button
                type="button"
                onClick={onOpen}
                className="font-medium text-indigo-600 underline hover:underline"
            >
                Add sample {label}
            </button>{' '}
            - you choose which ones.
        </p>
    );
}
