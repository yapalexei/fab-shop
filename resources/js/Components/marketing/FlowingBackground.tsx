import { useEffect, useRef } from 'react';

const vertexShader = /* glsl */ `
    uniform float uTime;
    uniform float uPixelRatio;
    varying float vHeight;

    void main() {
        vec3 p = position;
        float wave = sin(p.x * 0.35 + uTime * 0.8) * 0.9
                   + sin(p.y * 0.5 + uTime * 0.6) * 0.7
                   + sin((p.x + p.y) * 0.22 + uTime * 0.4) * 1.1;
        p.z = wave;
        vHeight = wave;

        vec4 mv = modelViewMatrix * vec4(p, 1.0);
        gl_PointSize = (2.2 + wave * 0.45) * uPixelRatio * (60.0 / -mv.z);
        gl_Position = projectionMatrix * mv;
    }
`;

const fragmentShader = /* glsl */ `
    uniform vec3 uColor;
    uniform float uOpacity;
    varying float vHeight;

    void main() {
        float d = length(gl_PointCoord - 0.5);
        if (d > 0.5) discard;
        float soft = smoothstep(0.5, 0.15, d);
        float crest = clamp(0.55 + vHeight * 0.18, 0.2, 1.0);
        gl_FragColor = vec4(uColor, soft * crest * uOpacity);
    }
`;

/**
 * A Vanta-style flowing field of dots drawn with three.js, behind the hero.
 * three is imported on demand so it never delays first paint, the loop
 * pauses off-screen and in hidden tabs, it follows the light/dark theme, and
 * it renders one still frame under prefers-reduced-motion. If WebGL isn't
 * available it silently renders nothing.
 */
export default function FlowingBackground() {
    const host = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = host.current;
        if (!el) return;

        let disposed = false;
        let cleanup = () => {};

        import('three')
            .then((THREE) => {
                if (disposed) return;

                const reduce = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;

                let renderer: InstanceType<typeof THREE.WebGLRenderer>;
                try {
                    renderer = new THREE.WebGLRenderer({
                        alpha: true,
                        antialias: false,
                    });
                } catch {
                    return;
                }

                const pixelRatio = Math.min(window.devicePixelRatio || 1, 1.5);
                renderer.setPixelRatio(pixelRatio);
                renderer.setClearColor(0x000000, 0);
                el.appendChild(renderer.domElement);
                renderer.domElement.style.display = 'block';

                const scene = new THREE.Scene();
                const camera = new THREE.PerspectiveCamera(55, 1, 0.1, 200);
                camera.position.set(0, -18, 14);
                camera.lookAt(0, 4, 0);

                // Grid of points on the XY plane, displaced in Z by the shader.
                const cols = 90;
                const rows = 56;
                const spacing = 0.9;
                const positions = new Float32Array(cols * rows * 3);
                for (let i = 0; i < cols; i++) {
                    for (let j = 0; j < rows; j++) {
                        const k = (i * rows + j) * 3;
                        positions[k] = (i - cols / 2) * spacing;
                        positions[k + 1] = (j - rows / 2) * spacing;
                        positions[k + 2] = 0;
                    }
                }
                const geometry = new THREE.BufferGeometry();
                geometry.setAttribute(
                    'position',
                    new THREE.BufferAttribute(positions, 3),
                );

                const material = new THREE.ShaderMaterial({
                    vertexShader,
                    fragmentShader,
                    transparent: true,
                    depthWrite: false,
                    uniforms: {
                        uTime: { value: 0 },
                        uPixelRatio: { value: pixelRatio },
                        uColor: { value: new THREE.Color('#4d9bf0') },
                        uOpacity: { value: 0.8 },
                    },
                });
                scene.add(new THREE.Points(geometry, material));

                const applyTheme = () => {
                    const dark =
                        document.documentElement.classList.contains('dark');
                    material.uniforms.uColor.value.set(
                        dark ? '#70adf3' : '#2f80d8',
                    );
                    material.uniforms.uOpacity.value = dark ? 0.75 : 0.55;
                };
                applyTheme();
                const themeObserver = new MutationObserver(() => {
                    applyTheme();
                    if (reduce) draw();
                });
                themeObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ['class'],
                });

                const resize = () => {
                    const w = el.clientWidth;
                    const h = el.clientHeight;
                    renderer.setSize(w, h);
                    camera.aspect = w / Math.max(h, 1);
                    camera.updateProjectionMatrix();
                };
                resize();
                const resizeObserver = new ResizeObserver(() => {
                    resize();
                    if (reduce) draw();
                });
                resizeObserver.observe(el);

                // Gentle camera drift toward the pointer.
                const pointer = { x: 0, y: 0 };
                const onPointer = (e: PointerEvent) => {
                    pointer.x = (e.clientX / window.innerWidth - 0.5) * 2;
                    pointer.y = (e.clientY / window.innerHeight - 0.5) * 2;
                };
                if (!reduce) window.addEventListener('pointermove', onPointer);

                const clock = new THREE.Clock();
                let frame = 0;
                let visible = true;

                const draw = () => {
                    material.uniforms.uTime.value = clock.getElapsedTime();
                    camera.position.x +=
                        (pointer.x * 3 - camera.position.x) * 0.03;
                    camera.position.y +=
                        (-18 - pointer.y * 2 - camera.position.y) * 0.03;
                    camera.lookAt(0, 4, 0);
                    renderer.render(scene, camera);
                };

                const loop = () => {
                    frame = requestAnimationFrame(loop);
                    draw();
                };
                const start = () => {
                    if (!frame && !reduce && visible && !document.hidden)
                        loop();
                };
                const stop = () => {
                    cancelAnimationFrame(frame);
                    frame = 0;
                };

                const intersection = new IntersectionObserver(([entry]) => {
                    visible = entry.isIntersecting;
                    visible ? start() : stop();
                });
                intersection.observe(el);
                const onVisibility = () => (document.hidden ? stop() : start());
                document.addEventListener('visibilitychange', onVisibility);

                // Fade in once the first frame exists.
                draw();
                el.style.opacity = '1';
                start();

                cleanup = () => {
                    stop();
                    intersection.disconnect();
                    resizeObserver.disconnect();
                    themeObserver.disconnect();
                    window.removeEventListener('pointermove', onPointer);
                    document.removeEventListener(
                        'visibilitychange',
                        onVisibility,
                    );
                    geometry.dispose();
                    material.dispose();
                    renderer.dispose();
                    renderer.domElement.remove();
                };
            })
            .catch(() => {});

        return () => {
            disposed = true;
            cleanup();
        };
    }, []);

    return (
        <div
            ref={host}
            aria-hidden="true"
            className="pointer-events-none absolute inset-0 -z-20 opacity-0 transition-opacity duration-1000 [mask-image:linear-gradient(to_bottom,black_55%,transparent)]"
        />
    );
}
