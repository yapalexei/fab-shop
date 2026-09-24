import { useCan } from '@/lib/permissions';
import { router } from '@inertiajs/react';
import { useRef } from 'react';

export interface JobPhoto {
    id: number;
    url: string;
    thumb_url: string;
}

export default function JobPhotos({
    jobId,
    photos,
}: {
    jobId: number;
    photos: JobPhoto[];
}) {
    const canEdit = useCan()('jobs.update');
    const fileInput = useRef<HTMLInputElement>(null);

    const upload = (files: FileList | null) => {
        if (!files || files.length === 0) return;

        const data = new FormData();
        Array.from(files).forEach((file) => data.append('photos[]', file));

        router.post(route('jobs.photos.store', jobId), data, {
            preserveScroll: true,
            onFinish: () => {
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const destroy = (photoId: number) => {
        if (!confirm('Delete this photo?')) return;

        router.delete(route('jobs.photos.destroy', [jobId, photoId]), {
            preserveScroll: true,
        });
    };

    return (
        <div className="bg-white p-6 shadow sm:rounded-lg">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-500">
                    Photos
                </h3>
                {canEdit && (
                    <label className="cursor-pointer rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                        Upload
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(e) => upload(e.target.files)}
                        />
                    </label>
                )}
            </div>

            {photos.length === 0 ? (
                <p className="text-sm text-gray-500">No photos yet.</p>
            ) : (
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                    {photos.map((photo) => (
                        <div key={photo.id} className="group relative">
                            <a href={photo.url} target="_blank" rel="noreferrer">
                                <img
                                    src={photo.thumb_url}
                                    alt="Job progress"
                                    className="aspect-square w-full rounded-md object-cover"
                                />
                            </a>
                            {canEdit && (
                                <button
                                    type="button"
                                    onClick={() => destroy(photo.id)}
                                    className="absolute right-1 top-1 hidden rounded-full bg-white/90 px-1.5 text-xs text-red-600 shadow group-hover:block"
                                >
                                    &times;
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
