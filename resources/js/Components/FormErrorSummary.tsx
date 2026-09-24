export default function FormErrorSummary({
    errors,
}: {
    errors: Record<string, string>;
}) {
    const messages = Object.values(errors);

    if (messages.length === 0) {
        return null;
    }

    return (
        <div className="mb-4 rounded-md border border-red-200 bg-red-50 p-4">
            <p className="text-sm font-medium text-red-800">
                {messages.length === 1
                    ? 'There is a problem with your submission:'
                    : `There are ${messages.length} problems with your submission:`}
            </p>
            <ul className="mt-2 list-disc space-y-1 ps-5 text-sm text-red-700">
                {messages.map((message, index) => (
                    <li key={index}>{message}</li>
                ))}
            </ul>
        </div>
    );
}
