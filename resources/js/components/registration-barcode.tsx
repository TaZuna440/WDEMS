import JsBarcode from 'jsbarcode';
import { useEffect, useRef } from 'react';

/** Encode only the lookup code; participant details never enter the barcode. */
export default function RegistrationBarcode({ code }: { code: string }) {
    const svg = useRef<SVGSVGElement>(null);
    useEffect(() => {
        if (!svg.current) return;
        JsBarcode(svg.current, code, {
            format: 'CODE128', width: 2, height: 80, margin: 20,
            background: '#ffffff', lineColor: '#000000', displayValue: false,
        });
    }, [code]);

    return <div className="flex justify-center overflow-x-auto rounded-lg bg-white p-2">
        <svg ref={svg} role="img" aria-label={`Registration barcode ${code}`} className="max-w-full" />
    </div>;
}
