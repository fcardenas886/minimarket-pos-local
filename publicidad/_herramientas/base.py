"""Base para reels animados: keyframes CSS deterministas (se graban fotograma a fotograma con grabar.mjs)."""
LOGO = "/_blob/c73b54504f0459cf99e2093a1fb6c4ca"   # logo SoluTI subido al lienzo
LOGO_LOCAL = r"C:\laragon\www\portafolio\assets\marca\soluti-logo-principal-para-fondo-oscuro.svg"
WSP = "+56 9 6186 2264"
EASE = "cubic-bezier(.2,.8,.2,1)"
H1 = "margin:0;font-family:Unbounded,sans-serif;font-weight:700;letter-spacing:-2px;line-height:1.05"

HID = {
    "up": "opacity:0;transform:translateY(70px)",
    "down": "opacity:0;transform:translateY(-70px)",
    "pop": "opacity:0;transform:scale(.55)",
    "left": "opacity:0;transform:translateX(-90px)",
    "right": "opacity:0;transform:translateX(90px)",
    "fade": "opacity:0;transform:none",
    "drop": "opacity:0;transform:translateY(-420px)",
    "strike": "opacity:1;transform:scaleX(0)",
}
VIS = {"strike": "opacity:1;transform:scaleX(1)"}


class Reel:
    def __init__(self, D):
        self.D, self.css, self.n = D, [], 0

    def pct(self, t):
        return f"{max(0, min(100, t / self.D * 100)):.3f}%"

    def anim(self, kind, tin, tout=None, dur=0.5):
        """Clase CSS: oculto -> visible en tin (dura dur), y se va en tout."""
        self.n += 1
        name = f"k{self.n}"
        h, v = HID[kind], VIS.get(kind, "opacity:1;transform:none")
        fr = [f"0%{{{h}}}", f"{self.pct(tin)}{{{h};animation-timing-function:{EASE}}}", f"{self.pct(tin + dur)}{{{v}}}"]
        if tout is not None and tout < self.D:
            fr += [f"{self.pct(tout - 0.3)}{{{v};animation-timing-function:ease-in}}",
                   f"{self.pct(tout)}{{opacity:0;transform:translateY(-40px)}}", "100%{opacity:0}"]
        else:
            fr.append(f"100%{{{v}}}")
        self.css.append(f"@keyframes {name}{{{''.join(fr)}}}.{name}{{animation:{name} {self.D}s linear infinite both}}")
        return name

    def first(self, tout):
        self.n += 1
        name = f"k{self.n}"
        self.css.append(f"@keyframes {name}{{0%{{opacity:1}}{self.pct(tout - .3)}{{opacity:1}}{self.pct(tout)}{{opacity:0}}100%{{opacity:0}}}}"
                        f".{name}{{animation:{name} {self.D}s linear infinite both}}")
        return name

    def sc(self, tin, tout, inner, bg="transparent"):
        cls = self.anim("fade", tin, tout, .3) if tin > 0 else self.first(tout)
        return f'<div class="{cls}" style="position:absolute;inset:0;background:{bg}">{inner}</div>'

    def page(self, title, body):
        D = self.D
        base = (f"@keyframes glow{{0%{{transform:translate(0,0)}}50%{{transform:translate(-260px,420px)}}100%{{transform:translate(0,0)}}}}"
                f".glow{{animation:glow {D}s ease-in-out infinite}}"
                f"@keyframes bar{{0%{{transform:scaleX(0)}}100%{{transform:scaleX(1)}}}}.bar{{animation:bar {D}s linear infinite;transform-origin:left}}")
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;family=Unbounded:wght@600;700&amp;display=swap" rel="stylesheet">
<style>
body{{margin:0}}
{base}
{"".join(self.css)}
</style>
</helmet>
<div style="width: 1080px; height: 1920px; position: relative; overflow: hidden; background: #070B18; color: #F5F7FF; font-family: Outfit, sans-serif">
<div class="glow" style="position:absolute;width:1100px;height:1100px;right:-420px;top:-300px;border-radius:50%;background:radial-gradient(circle,rgba(59,108,255,.38),rgba(59,108,255,0) 65%)"></div>
<div class="glow" style="position:absolute;width:900px;height:900px;left:-380px;bottom:-200px;border-radius:50%;background:radial-gradient(circle,rgba(34,211,238,.18),rgba(34,211,238,0) 65%);animation-direction:reverse"></div>
{body}
<div style="position:absolute;left:90px;right:90px;top:120px;height:8px;border-radius:4px;background:rgba(255,255,255,.12);overflow:hidden"><div class="bar" style="height:100%;background:#22D3EE"></div></div>
</div>
</x-dc>
<script type="text/x-dc" data-dc-script data-props='{{"$preview":{{"width":1080,"height":1920}}}}'>
class Component extends DCLogic {{
renderVals() {{
return {{}};
}}
}}
</script>
</body>
</html>
'''


def a_html_local(dc_html):
    """Convierte un .dc.html del lienzo en HTML normal para grabarlo con Chrome."""
    import re, pathlib
    helmet = re.search(r"<helmet>(.*?)</helmet>", dc_html, re.S).group(1)
    body = re.sub(r"<helmet>.*?</helmet>", "", re.search(r"<x-dc>(.*?)</x-dc>", dc_html, re.S).group(1), flags=re.S)
    page = f'<!doctype html><html lang="es"><head><meta charset="utf-8">{helmet}</head><body>{body}</body></html>'
    return page.replace(LOGO, pathlib.Path(LOGO_LOCAL).as_uri())
