import {
    AmbientLight,
    Box3,
    BufferGeometry,
    DirectionalLight,
    GridHelper,
    Group,
    Mesh,
    MeshStandardMaterial,
    Object3D,
    PerspectiveCamera,
    Scene,
    Vector3,
    WebGLRenderer,
} from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { ThreeMFLoader } from 'three/examples/jsm/loaders/3MFLoader.js';
import { OBJLoader } from 'three/examples/jsm/loaders/OBJLoader.js';
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';
import { useEffect, useRef, useState } from 'react';

/** Kinds this viewer can render in the browser. */
export const VIEWABLE_KINDS = ['stl', 'obj', '3mf'];

const material = () =>
    new MeshStandardMaterial({
        color: 0x9aa4b2,
        metalness: 0.4,
        roughness: 0.5,
    });

async function loadModel(kind: string, url: string): Promise<Object3D> {
    const buffer = await fetch(url, { credentials: 'same-origin' }).then(
        (res) => {
            if (!res.ok) throw new Error(`Could not load file (${res.status})`);
            return res.arrayBuffer();
        },
    );

    if (kind === 'stl') {
        const geometry: BufferGeometry = new STLLoader().parse(buffer);
        geometry.computeVertexNormals();
        return new Mesh(geometry, material());
    }

    if (kind === 'obj') {
        const group = new OBJLoader().parse(new TextDecoder().decode(buffer));
        group.traverse((child) => {
            if (child instanceof Mesh) child.material = material();
        });
        return group;
    }

    return new ThreeMFLoader().parse(buffer);
}

/**
 * Orbit/zoom viewer for one model file. CAD files are Z-up, so the camera is
 * too. Everything it allocates is disposed on unmount.
 */
export default function ModelViewer({
    kind,
    url,
}: {
    kind: string;
    url: string;
}) {
    const container = useRef<HTMLDivElement>(null);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>(
        'loading',
    );
    const [message, setMessage] = useState('');

    useEffect(() => {
        const el = container.current;
        if (!el) return;

        let cancelled = false;
        let frame = 0;

        const renderer = new WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(window.devicePixelRatio);
        el.appendChild(renderer.domElement);

        const scene = new Scene();
        const camera = new PerspectiveCamera(45, 1, 0.1, 10000);
        camera.up.set(0, 0, 1);

        scene.add(new AmbientLight(0xffffff, 0.8));
        const key = new DirectionalLight(0xffffff, 2);
        key.position.set(1, -1, 2);
        scene.add(key);
        const fill = new DirectionalLight(0xffffff, 0.8);
        fill.position.set(-1, 1, -1);
        scene.add(fill);

        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;

        const resize = () => {
            const { clientWidth: w, clientHeight: h } = el;
            if (w === 0 || h === 0) return;
            renderer.setSize(w, h);
            camera.aspect = w / h;
            camera.updateProjectionMatrix();
        };
        const observer = new ResizeObserver(resize);
        observer.observe(el);
        resize();

        const animate = () => {
            frame = requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        };
        animate();

        loadModel(kind, url)
            .then((model) => {
                if (cancelled) return;

                const holder = new Group();
                holder.add(model);

                // Centre on the origin and sit on the grid.
                const box = new Box3().setFromObject(holder);
                const center = box.getCenter(new Vector3());
                const size = box.getSize(new Vector3());
                model.position.sub(center);
                model.position.z += size.z / 2;
                scene.add(holder);

                const radius = Math.max(size.x, size.y, size.z) || 1;
                const grid = new GridHelper(radius * 3, 20, 0x888888, 0xcccccc);
                grid.rotation.x = Math.PI / 2;
                scene.add(grid);

                camera.position.set(radius * 1.6, -radius * 1.6, radius * 1.2);
                camera.near = radius / 100;
                camera.far = radius * 100;
                camera.updateProjectionMatrix();
                controls.target.set(0, 0, size.z / 2);
                controls.update();

                setStatus('ready');
            })
            .catch((error: Error) => {
                if (cancelled) return;
                setMessage(error.message || 'Could not display this file.');
                setStatus('error');
            });

        return () => {
            cancelled = true;
            cancelAnimationFrame(frame);
            observer.disconnect();
            controls.dispose();
            scene.traverse((object) => {
                if (object instanceof Mesh) {
                    object.geometry.dispose();
                    const mats = Array.isArray(object.material)
                        ? object.material
                        : [object.material];
                    mats.forEach((m) => m.dispose());
                }
            });
            renderer.dispose();
            renderer.domElement.remove();
        };
    }, [kind, url]);

    return (
        <div className="relative h-[60vh] w-full overflow-hidden rounded-md border bg-muted">
            <div ref={container} className="size-full" />
            {status !== 'ready' && (
                <p className="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                    {status === 'loading' ? 'Loading model…' : message}
                </p>
            )}
        </div>
    );
}
