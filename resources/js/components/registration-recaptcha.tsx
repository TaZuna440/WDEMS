import { useEffect, useRef, useState } from 'react';

type CaptchaApi = {
    render: (element: HTMLElement, options: Record<string, unknown>) => number;
    reset: (id: number) => void;
};
type CaptchaWindow = Window & {
    grecaptcha?: CaptchaApi;
    wdemsRecaptchaReady?: () => void;
};
let loading: Promise<CaptchaApi> | null = null;

function logCaptchaError(stage: string, error: unknown) {
    const detail =
        error instanceof Error
            ? { name: error.name, message: error.message }
            : { name: 'UnknownError' };
    console.error(`registration.recaptcha.${stage}_failed`, detail);
}

function loadCaptcha(): Promise<CaptchaApi> {
    const target = window as CaptchaWindow;
    if (target.grecaptcha?.render) return Promise.resolve(target.grecaptcha);
    if (!loading) {
        const promise = new Promise<CaptchaApi>((resolve, reject) => {
            target.wdemsRecaptchaReady = () =>
                target.grecaptcha
                    ? resolve(target.grecaptcha)
                    : reject(new Error('reCAPTCHA unavailable'));
            const script = document.createElement('script');
            script.src =
                'https://www.recaptcha.net/recaptcha/api.js?onload=wdemsRecaptchaReady&render=explicit';
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                script.remove();
                reject(
                    new Error(
                        'Could not load human verification. Reload to retry.',
                    ),
                );
            };
            document.head.appendChild(script);
        }).catch((error: unknown) => {
            loading = null;
            throw error;
        });
        loading = promise;
        return promise;
    }
    return loading;
}

export default function RegistrationRecaptcha({
    siteKey,
    resetKey,
    onToken,
}: {
    siteKey: string | null;
    resetKey: number;
    onToken: (token: string) => void;
}) {
    const element = useRef<HTMLDivElement>(null);
    const callback = useRef(onToken);
    callback.current = onToken;
    const widget = useRef<{ api: CaptchaApi; id: number } | null>(null);
    const previousResetKey = useRef(resetKey);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!siteKey) return;
        let active = true;
        const container = element.current;
        if (!container) return;

        const mount = document.createElement('div');
        container.replaceChildren(mount);
        let renderedWidget: { api: CaptchaApi; id: number } | null = null;

        loadCaptcha()
            .then((loaded) => {
                if (!active || mount.parentElement !== container) return;
                try {
                    const id = loaded.render(mount, {
                        sitekey: siteKey,
                        callback: (token: string) => {
                            if (!active) return;
                            setError('');
                            callback.current(token);
                        },
                        'expired-callback': () => {
                            if (active) callback.current('');
                        },
                        'error-callback': () => {
                            if (!active) return;
                            callback.current('');
                            setError(
                                'Human verification failed. Reload to retry.',
                            );
                        },
                    });
                    renderedWidget = { api: loaded, id };
                    widget.current = renderedWidget;
                } catch (error: unknown) {
                    logCaptchaError('widget_initialization', error);
                    if (active)
                        setError('Could not initialize human verification.');
                }
            })
            .catch((error: unknown) => {
                logCaptchaError('script_load', error);
                if (active)
                    setError(
                        'Could not load human verification. Reload to retry.',
                    );
            });
        return () => {
            active = false;
            if (widget.current === renderedWidget) widget.current = null;
            mount.remove();
        };
    }, [siteKey]);
    useEffect(() => {
        if (previousResetKey.current === resetKey) return;
        previousResetKey.current = resetKey;
        if (widget.current) {
            widget.current.api.reset(widget.current.id);
        }
    }, [resetKey]);
    return (
        <div className="space-y-2">
            {!siteKey && (
                <p role="alert">
                    Human verification is not configured. Please contact the
                    organizer.
                </p>
            )}
            {error && (
                <p role="alert" className="text-destructive text-sm">
                    {error}
                </p>
            )}
            <div ref={element} />
        </div>
    );
}
