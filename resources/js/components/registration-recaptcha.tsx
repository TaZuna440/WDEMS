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

function loadCaptcha(): Promise<CaptchaApi> {
    const target = window as CaptchaWindow;
    if (target.grecaptcha?.render) return Promise.resolve(target.grecaptcha);
    if (!loading)
        loading = new Promise((resolve, reject) => {
            target.wdemsRecaptchaReady = () =>
                target.grecaptcha
                    ? resolve(target.grecaptcha)
                    : reject(new Error('reCAPTCHA unavailable'));
            const script = document.createElement('script');
            script.src =
                'https://www.google.com/recaptcha/api.js?onload=wdemsRecaptchaReady&render=explicit';
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                loading = null;
                script.remove();
                reject(
                    new Error(
                        'Could not load human verification. Reload to retry.',
                    ),
                );
            };
            document.head.appendChild(script);
        });
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
    const [error, setError] = useState('');
    useEffect(() => {
        if (!siteKey) return;
        let active = true;
        let widget: number | undefined;
        let api: CaptchaApi | undefined;
        const container = element.current;
        loadCaptcha()
            .then((loaded) => {
                if (!active || !container) return;
                api = loaded;
                widget = loaded.render(container, {
                    sitekey: siteKey,
                    callback: (token: string) => callback.current(token),
                    'expired-callback': () => callback.current(''),
                    'error-callback': () => {
                        callback.current('');
                        setError('Human verification failed. Reload to retry.');
                    },
                });
            })
            .catch(() => {
                if (active)
                    setError(
                        'Could not load human verification. Reload to retry.',
                    );
            });
        return () => {
            active = false;
            if (widget !== undefined) api?.reset(widget);
            container?.replaceChildren();
        };
    }, [siteKey, resetKey]);
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
