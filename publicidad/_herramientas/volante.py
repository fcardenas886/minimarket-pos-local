"""Volante imprimible A5 (frente y reverso) + PDFs A5 y A4 (2 por hoja).
Uso: python volante.py <carpeta project del lienzo> <carpeta salida>"""
import json, pathlib, re, subprocess, sys
import segno

proj, out = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2]).resolve()
out.mkdir(parents=True, exist_ok=True)
tmp = pathlib.Path(__file__).with_name("tmp"); tmp.mkdir(exist_ok=True)
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
WSP = "+56 9 6186 2264"
W, H = 559, 794   # A5 a 96 ppp (148 x 210 mm)

logo_src = pathlib.Path(r"C:\laragon\www\portafolio\assets\marca\soluti-logo-principal-para-fondo-oscuro.svg").read_text(encoding="utf-8")
def logo(width, claro=True):
    s = re.sub(r'\swidth="[^"]*"\sheight="[^"]*"', "", logo_src, count=1)
    if claro:
        s = s.replace('fill="#F5F7FF"', 'fill="#070B18"').replace('fill="#8F9BBF"', 'fill="#4A5578"')
    return s.replace("<svg ", f'<svg style="width:{width}px;height:auto;display:block" aria-label="SoluTI" role="img" ', 1)

qr = segno.make("https://wa.me/56961862264?text=Hola%20SoluTI%2C%20quiero%20m%C3%A1s%20informaci%C3%B3n", error="m")
qr.save(str(tmp / "qr.svg"), scale=10, border=0, dark="#070B18")
qsvg = re.sub(r"<\?xml[^>]*>", "", (tmp / "qr.svg").read_text())
n = int(re.search(r'width="(\d+)"', qsvg).group(1))
def qrc(s):
    return re.sub(r'width="\d+" height="\d+"', f'width="{s}" height="{s}" viewBox="0 0 {n} {n}"', qsvg, count=1)

ICON = {
    "scan": '<path d="M3 7V4h3 M18 4h3v3 M21 17v3h-3 M6 20H3v-3 M7 8v8 M10 8v8 M13 8v8 M16 8v8"/>',
    "caja": '<path d="M3 8l9-5 9 5v9l-9 5-9-5z M3 8l9 5 9-5 M12 13v9"/>',
    "recibo": '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z M9 8h6 M9 12h6"/>',
    "code": '<path d="M8 8l-4 4 4 4 M16 8l4 4-4 4 M13.5 5l-3 14"/>',
    "x": '<path d="M6 6l12 12 M18 6L6 18"/>',
    "check": '<path d="M4 12.5l5 5L20 6.5"/>',
    "pin": '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    "wsp": '<path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.4A8.4 8.4 0 1 1 21 11.5z"/>',
}
def ic(k, col, s, w=2.2):
    return (f'<svg width="{s}" height="{s}" viewBox="0 0 24 24" fill="none" stroke="{col}" stroke-width="{w}" stroke-linecap="round" '
            f'stroke-linejoin="round" aria-hidden="true">{ICON[k]}</svg>')

FONT = ('<link rel="preconnect" href="https://fonts.googleapis.com">'
        '<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;family=Unbounded:wght@600;700&amp;display=swap" rel="stylesheet">')
U = "font-family:Unbounded,sans-serif;font-weight:700"

# ---------- FRENTE ----------
probs = ["Ventas en cuaderno", "Stock “a ojo”", "Boletas a mano", "La caja no cuadra"]
pz = "".join(f'<div style="display:flex;align-items:center;gap:8px;background:#FFF1F3;border:1.5px solid #FFD0D8;border-radius:10px;padding:11px 12px;font-size:14px;font-weight:600;color:#3A1520">'
             f'{ic("x", "#E10A3B", 16, 2.8)}{p}</div>' for p in probs)
servs = [("scan", "Punto de venta"), ("caja", "Inventario"), ("recibo", "Boleta electrónica"), ("code", "Sistemas a medida")]
sv = "".join(f'<div style="display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;font-size:12.5px;font-weight:600;line-height:1.2;color:#070B18">'
             f'<div style="width:54px;height:54px;border-radius:14px;background:#EAF0FF;display:flex;align-items:center;justify-content:center">{ic(k, "#3B6CFF", 30)}</div>{t}</div>' for k, t in servs)
frente = f'''<div style="width:{W}px;height:{H}px;box-sizing:border-box;position:relative;overflow:hidden;background:#FFFFFF;color:#070B18;font-family:Outfit,sans-serif;padding:30px 30px 0;display:flex;flex-direction:column;gap:19px">
<div style="display:flex;justify-content:space-between;align-items:center;position:relative">{logo(190)}
<span style="display:flex;align-items:center;gap:5px;background:#F2B705;font-size:12px;font-weight:700;padding:6px 11px;border-radius:999px">{ic("pin", "#070B18", 14, 2.6)}Loncoche</span></div>
<h1 style="margin:6px 0 0;{U};font-size:39px;line-height:1.08;letter-spacing:-1px">¿Tu negocio necesita <span style="background:#22D3EE;padding:0 7px;border-radius:8px">orden?</span></h1>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">{pz}</div>
<p style="margin:0;font-size:15px;line-height:1.45;color:#2B3555">…y no sabes a quién acudir. Ya no tienes que ir a Temuco ni a Santiago:</p>
<div style="background:#070B18;color:#F5F7FF;border-radius:16px;padding:22px 22px;display:flex;flex-direction:column;gap:8px">
<span style="{U};font-size:25px;line-height:1.15;letter-spacing:-.5px">Ahora en Loncoche<br>existe <span style="color:#22D3EE">SoluTI</span>.</span>
<span style="font-size:14px;line-height:1.45;color:#C9D0E6">Soluciones Tecnológicas Integrales: tecnología al alcance de las pymes del sur de Chile.</span></div>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:2px">{sv}</div>
<div style="margin-top:auto;margin-left:-30px;margin-right:-30px;background:#F5F7FF;border-top:2px solid #E1E7F5;padding:16px 30px 22px;display:flex;align-items:center;gap:16px">
<div style="background:#fff;padding:6px;border-radius:8px;border:1.5px solid #DCE2F2">{qrc(84)}</div>
<div style="display:flex;flex-direction:column;gap:3px">
<span style="font-size:12px;font-weight:600;color:#4A5578">Escanea o escríbenos por WhatsApp</span>
<span style="display:flex;align-items:center;gap:6px;{U};font-size:22px;letter-spacing:-.5px">{ic("wsp", "#22A06B", 20, 2.6)}{WSP}</span>
<span style="font-size:12px;color:#4A5578">Conversemos · Propuesta sin compromiso</span></div></div>
</div>'''

# ---------- REVERSO ----------
feats = [("Escáner y balanza", "Vende rápido, también a granel"), ("Promociones", "3x2 y descuentos con fecha"),
         ("Fiados y puntos", "Cuenta por cliente"), ("Boleta y factura", "Electrónica, directo al SII"),
         ("Arqueo y Cierre Z", "La caja cuadra cada día"), ("Alertas de stock", "Compra antes de que se acabe"),
         ("Utilidades", "Qué te deja plata y qué no"), ("Estimador F29", "El IVA sin sorpresas"),
         ("Sin internet", "Sigues vendiendo y sincroniza"), ("Varias cajas", "Y acceso desde donde estés")]
fz = "".join(f'<div style="display:flex;gap:8px;align-items:flex-start"><div style="flex-shrink:0;width:20px;height:20px;border-radius:50%;background:#22D3EE;display:flex;align-items:center;justify-content:center;margin-top:1px">'
             f'{ic("check", "#070B18", 13, 3.4)}</div><div style="display:flex;flex-direction:column"><b style="font-size:14.5px">{a}</b><span style="font-size:12.5px;color:#4A5578;line-height:1.3">{b}</span></div></div>'
             for a, b in feats)
pasos = [("Conversamos", "Nos cuentas cómo funciona tu negocio"), ("Propuesta", "Qué incluye, plazos y costo"),
         ("Funcionando", "Computador, tablet o celular"), ("Te acompañamos", "Soporte en la zona y remoto")]
pz2 = "".join(f'<div style="display:flex;flex-direction:column;gap:4px"><span style="{U};font-size:18px;color:#3B6CFF">0{i + 1}</span>'
              f'<b style="font-size:13.5px;line-height:1.2">{a}</b><span style="font-size:11.5px;color:#4A5578;line-height:1.3">{b}</span></div>' for i, (a, b) in enumerate(pasos))
reverso = f'''<div style="width:{W}px;height:{H}px;box-sizing:border-box;position:relative;overflow:hidden;background:#FFFFFF;color:#070B18;font-family:Outfit,sans-serif;padding:30px 30px 0;display:flex;flex-direction:column;gap:16px">
<div style="display:flex;flex-direction:column;gap:4px">
<span style="align-self:flex-start;font-size:11px;font-weight:700;letter-spacing:1px;color:#3B6CFF;background:#EAF0FF;padding:5px 10px;border-radius:999px">MARKETSTI · PUNTO DE VENTA EN LA NUBE</span>
<h2 style="margin:6px 0 0;{U};font-size:27px;line-height:1.12;letter-spacing:-.6px">Todo tu negocio en <span style="color:#3B6CFF">un solo sistema</span>.</h2>
<span style="font-size:13px;color:#4A5578">Para almacenes, minimarkets, botillerías, ferreterías y más.</span></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 16px;background:#F5F7FF;border:1.5px solid #E1E7F5;border-radius:16px;padding:20px 18px">{fz}</div>
<div style="display:flex;flex-direction:column;gap:10px">
<span style="{U};font-size:16px">Cómo trabajamos</span>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;border-top:2px solid #E1E7F5;padding-top:12px">{pz2}</div></div>
<p style="margin:0;font-size:13.5px;line-height:1.45;color:#2B3555">¿Necesitas algo distinto? También hacemos <b>sistemas a medida</b> para talleres, gimnasios, condominios, repartos y más.</p>
<div style="margin-top:auto;margin-left:-30px;margin-right:-30px;background:#070B18;color:#F5F7FF;padding:18px 30px 24px;display:flex;align-items:center;gap:16px">
<div style="background:#fff;padding:6px;border-radius:8px">{qrc(76)}</div>
<div style="display:flex;flex-direction:column;gap:3px;flex-grow:1">
<span style="font-size:12px;color:#A3ADCC">Pide tu demostración</span>
<span style="{U};font-size:21px;letter-spacing:-.4px;color:#22D3EE">{WSP}</span>
<span style="font-size:11.5px;color:#A3ADCC">SoluTI · Loncoche, Región de La Araucanía</span></div>
{logo(120, claro=False)}</div>
</div>'''

def dc(title, inner):
    return f'''<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>{title}</title>
<script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
{FONT}
<style>
body{{margin:0}}
</style>
</helmet>
{inner}
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{{"$preview":{{"width":{W},"height":{H}}}}}'>
class Component extends DCLogic {{
renderVals() {{
return {{}};
}}
}}
</script>
</body>
</html>
'''

(proj / "Volante_Frente.dc.html").write_text(dc("Volante frente", frente), encoding="utf-8")
(proj / "Volante_Reverso.dc.html").write_text(dc("Volante reverso", reverso), encoding="utf-8")
c = json.loads((proj / "canvas.json").read_text(encoding="utf-8"))
c["boards"]["Volante_Frente.dc.html"] = {"x": 0, "y": 13310, "w": W, "h": H, "title": "Volante A5 · Frente"}
c["boards"]["Volante_Reverso.dc.html"] = {"x": 640, "y": 13310, "w": W, "h": H, "title": "Volante A5 · Reverso"}
for k in ("Volante_Frente.dc.html", "Volante_Reverso.dc.html"):
    if k not in c["order"]:
        c["order"].append(k)
c["notes"]["filaImpresos"] = {"x": 0, "y": 13050, "text": "Sexto: impresos para locales", "kind": "title1", "maxW": 1200}
(proj / "canvas.json").write_text(json.dumps(c, ensure_ascii=False), encoding="utf-8")


def imprimir(nombre, paginas, ancho_mm, alto_mm):
    css = (f"@page{{size:{ancho_mm}mm {alto_mm}mm;margin:0}}body{{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}}"
           f".pg{{width:{ancho_mm}mm;height:{alto_mm}mm;display:flex;align-items:center;justify-content:center;gap:0;page-break-after:always;position:relative;overflow:hidden}}")
    body = "".join(f'<div class="pg">{p}</div>' for p in paginas)
    h = tmp / f"{nombre}.html"
    h.write_text(f'<!doctype html><html lang="es"><head><meta charset="utf-8">{FONT}<style>{css}</style></head><body>{body}</body></html>', encoding="utf-8")
    pdf = out / f"{nombre}.pdf"
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--no-pdf-header-footer", "--virtual-time-budget=8000",
                    f"--print-to-pdf={pdf}", h.as_uri()], check=True, capture_output=True, timeout=120)
    print("pdf", pdf)


corte = '<div style="position:absolute;left:50%;top:0;bottom:0;border-left:1px dashed #B9C2DE"></div>'
imprimir("volante_A5_frente_y_reverso", [frente, reverso], 148, 210)
imprimir("volante_A4_2_por_hoja", [frente + frente + corte, reverso + reverso + corte], 297, 210)

for nombre, inner in (("volante_frente", frente), ("volante_reverso", reverso)):
    h = tmp / f"{nombre}.html"
    h.write_text(f'<!doctype html><html lang="es"><head><meta charset="utf-8">{FONT}<style>body{{margin:0}}</style></head><body>{inner}</body></html>', encoding="utf-8")
    subprocess.run([CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars", "--force-device-scale-factor=3", f"--window-size={W},{H}",
                    "--virtual-time-budget=8000", f"--screenshot={out / (nombre + '.png')}", h.as_uri()], check=True, capture_output=True, timeout=120)
    print("png", out / f"{nombre}.png")
