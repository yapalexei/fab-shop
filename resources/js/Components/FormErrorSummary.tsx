import { Alert } from '@/Components/ui/alert';
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
        <Alert variant="danger" className="mb-4 p-4">
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
        </Alert>
    );
}
