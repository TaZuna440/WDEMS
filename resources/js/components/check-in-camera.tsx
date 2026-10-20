import { Camera } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Detector = { detect: (image: HTMLVideoElement) => Promise<{ rawValue: string }[]> };
type DetectorClass = { new(options: { formats: string[] }): Detector; getSupportedFormats: () => Promise<string[]> };

export default function CheckInCamera({ onCode }: { onCode: (code: string) => void }) {
    const [open, setOpen] = useState(false);
    const [error, setError] = useState('');
    const video = useRef<HTMLVideoElement>(null);
    const callback = useRef(onCode);
    callback.current = onCode;
    useEffect(() => {
        if (!open) return;
        let cancelled = false;
        let stream: MediaStream | undefined;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const stop = () => stream?.getTracks().forEach((track) => track.stop());
        const start = async () => {
            try {
                const DetectorApi = (window as Window & { BarcodeDetector?: DetectorClass }).BarcodeDetector;
                if (!window.isSecureContext || !navigator.mediaDevices) throw new Error('Camera scanning requires HTTPS or localhost. You can still type or scan a code with a handheld scanner.');
                if (!DetectorApi || !(await DetectorApi.getSupportedFormats()).includes('code_128')) throw new Error('This browser does not support Code 128 camera scanning. Use a supported mobile browser or a handheld scanner.');
                if (cancelled) return;
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                if (cancelled) { stop(); return; }
                if (!video.current) { stop(); return; }
                video.current.srcObject = stream;
                await video.current.play();
                const detector = new DetectorApi({ formats: ['code_128'] });
                const scan = async () => {
                    if (cancelled || !video.current) return;
                    try {
                        const codes = video.current.readyState >= 2 ? await detector.detect(video.current) : [];
                        if (cancelled) return;
                        const match = codes.find(({ rawValue }) => /^[A-Z0-9]{4}-?[A-Z0-9]{4}$/i.test(rawValue.trim()));
                        if (match) {
                            cancelled = true; stop(); setOpen(false); callback.current(match.rawValue.trim()); return;
                        }
                    } catch {
                        if (!cancelled) setError('Could not read the camera image. Reopen the camera or enter the code manually.');
                        stop(); return;
                    }
                    timer = setTimeout(scan, 200);
                };
                if (!cancelled) void scan();
            } catch (reason) {
                stop();
                if (!cancelled) setError(reason instanceof Error && reason.name !== 'NotAllowedError' ? reason.message : 'Camera access was not allowed. You can still enter the code manually.');
            }
        };
        void start();
        return () => { cancelled = true; clearTimeout(timer); stop(); };
    }, [open]);

    return <>
        <Button type="button" variant="outline" onClick={() => { setError(''); setOpen(true); }}><Camera className="mr-2 h-4 w-4" />Scan with camera</Button>
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent><DialogHeader><DialogTitle>Scan registration barcode</DialogTitle><DialogDescription>Point your camera at the Code 128 barcode. Camera images stay on this device.</DialogDescription></DialogHeader>
                {error ? <p role="alert" className="text-sm text-destructive">{error}</p> : <video ref={video} muted playsInline autoPlay className="w-full rounded-lg bg-black" />}
            </DialogContent>
        </Dialog>
    </>;
}
