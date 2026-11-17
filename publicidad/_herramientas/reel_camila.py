"""Reel 'Quiénes somos': historia de Camila y su local.
Uso: python reel_camila.py <carpeta project del lienzo> <html_local_salida>"""
import json, pathlib, sys
from base import Reel, HID, LOGO, WSP, a_html_local

proj, local_out = pathlib.Path(sys.argv[1]), pathlib.Path(sys.argv[2])
D = 38
r = Reel(D)
r.css.append("@keyframes flo{0%,100%{transform:translateY(0)}50%{transform:translateY(-22px)}}.flo{animation:flo 1.6s ease-in-out infinite}"
             "@keyframes spin{to{transform:rotate(360deg)}}.spin{animation:spin 12s linear infinite;transform-origin:center;transform-box:fill-box}"
             ".spinfast{animation:spin .8s linear infinite;transform-origin:50% 100%;transform-box:fill-box}"
             "@keyframes wob{0%,100%{transform:rotate(-8deg)}50%{transform:rotate(8deg)}}.wob{animation:wob 1.2s ease-in-out infinite}"
             "@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}.pulse{animation:pulse 1.4s ease-in-out infinite}"
             "@keyframes road{to{background-position:-160px 0}}.road{animation:road .4s linear infinite}"
             "@keyframes bump{0%,100%{transform:translateY(0)}50%{transform:translateY(-5px)}}.bump{animation:bump .3s ease-in-out infinite}")

SKIN, HAIR = "#C98B62", "#3A2218"


def camila(mood):
    brows = ('<path d="M112 136 L138 128 M162 128 L188 136" stroke="#3A2218" stroke-width="5" stroke-linecap="round"/>' if mood == "worried"
             else '<path d="M114 130 Q126 121 140 128 M160 128 Q174 121 186 130" stroke="#3A2218" stroke-width="5" fill="none" stroke-linecap="round"/>')
    mouth = ('<path d="M136 204 Q150 194 164 204" stroke="#7A2E22" stroke-width="5" fill="none" stroke-linecap="round"/>' if mood == "worried"
             else '<path d="M130 194 Q150 216 170 194" stroke="#7A2E22" stroke-width="5" fill="#9B3B2E" stroke-linecap="round"/>')
    gota = '<g class="flo"><path d="M224 110 q10 16 0 24 q-10 -8 0 -24z" fill="#7DD3FC"/></g>' if mood == "worried" else ""
    return f'''<svg width="300" height="470" viewBox="0 0 300 470" aria-hidden="true"><g>
<path d="M78 150 Q70 300 96 330 L204 330 Q230 300 222 150Z" fill="{HAIR}"/>
<rect x="36" y="270" width="54" height="190" rx="27" fill="#E10A3B"/><rect x="210" y="270" width="54" height="190" rx="27" fill="#E10A3B"/>
<rect x="62" y="248" width="176" height="260" rx="64" fill="#E10A3B"/>
<rect x="96" y="300" width="108" height="210" rx="18" fill="#FFF7EC"/><path d="M104 300 L86 256 M196 300 L214 256" stroke="#FFF7EC" stroke-width="8"/>
<rect x="132" y="206" width="36" height="52" fill="{SKIN}"/>
<ellipse cx="150" cy="150" rx="60" ry="70" fill="{SKIN}"/>
<path d="M88 150 Q84 72 150 70 Q216 72 212 150 Q196 100 150 110 Q120 96 88 150Z" fill="{HAIR}"/>
<circle cx="90" cy="176" r="6" fill="#F2B705"/><circle cx="210" cy="176" r="6" fill="#F2B705"/>
{brows}<circle cx="128" cy="156" r="7" fill="#1B1B1B"/><circle cx="172" cy="156" r="7" fill="#1B1B1B"/>
<path d="M150 160 Q158 176 148 180" stroke="#9C6A48" stroke-width="5" fill="none" stroke-linecap="round"/>
<circle cx="118" cy="182" r="10" fill="#E88A7A" opacity=".5"/><circle cx="182" cy="182" r="10" fill="#E88A7A" opacity=".5"/>{mouth}{gota}</g></svg>'''


def titulo(t, html):
    return (f'<div class="{r.anim("down", t)}" style="position:absolute;left:90px;right:90px;top:200px;background:#0B1124;border:2px solid #24305A;'
            f'border-radius:28px;padding:30px 36px;text-align:center;font-family:Unbounded,sans-serif;font-weight:700;font-size:54px;line-height:1.2;letter-spacing:-1px;color:#F5F7FF">{html}</div>')


def at(x, y, t, inner, kind="up", dur=.5, cls_inner=""):
    return f'<div style="position:absolute;left:{x}px;top:{y}px"><div class="{r.anim(kind, t, dur=dur)}"><div class="{cls_inner}">{inner}</div></div></div>'


def mostrador(l, w, t):
    return f'<div style="position:absolute;left:{l}px;width:{w}px;top:{t}px;height:170px;background:#1A2547;border-top:22px solid #26345F;border-radius:10px"></div>'


C = '<span style="color:#22D3EE">{}</span>'
Y = '<span style="color:#F2B705">{}</span>'
ICON = {
    "chat": '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.1A8 8 0 1 1 21 12z" />',
    "doc": '<path d="M6 3h9l4 4v14H6z M15 3v4h4 M9 12h7 M9 16h5" />',
    "check": '<path d="M3 5h18v12H3z M8 21h8 M12 17v4 M8 11l3 3 5-5" />',
    "apoyo": '<path d="M4 14v-2a8 8 0 0 1 16 0v2 M4 14h3v5H4z M17 14h3v5h-3z M20 19a3 3 0 0 1-3 3h-3" />',
}


def ic(k, col="#A3ADCC", s=64):
    return f'<svg width="{s}" height="{s}" viewBox="0 0 24 24" fill="none" stroke="{col}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{ICON[k]}</svg>'


# 1. Su local (genérico)
prods = ""
for fila, y in enumerate((420, 500, 580)):
    prods += f'<rect x="100" y="{y + 52}" width="280" height="8" fill="#7A3E2B"/>'
    for j in range(5):
        x = 112 + j * 54
        col = ["#E10A3B", "#3B6CFF", "#F2B705", "#22A06B", "#F5F7FF"][(j + fila) % 5]
        if (j + fila) % 3 == 0:
            prods += f'<rect x="{x + 8}" y="{y + 6}" width="24" height="46" rx="8" fill="{col}"/><rect x="{x + 14}" y="{y - 4}" width="12" height="12" rx="3" fill="{col}"/>'
        elif (j + fila) % 3 == 1:
            prods += f'<rect x="{x}" y="{y + 14}" width="40" height="38" rx="4" fill="{col}"/><rect x="{x}" y="{y + 26}" width="40" height="8" fill="rgba(0,0,0,.15)"/>'
        else:
            prods += f'<rect x="{x + 4}" y="{y + 18}" width="32" height="34" rx="12" fill="{col}"/>'
toldo = "".join(f'<path d="M{40 + i * 82} 230 h82 v70 q-41 30 -82 0z" fill="{"#22D3EE" if i % 2 == 0 else "#F5F7FF"}"/>' for i in range(10))
local = f'''<svg width="900" height="760" viewBox="0 0 900 760" aria-hidden="true">
<g class="spin"><circle cx="790" cy="90" r="48" fill="#FFD23F"/></g>
<rect x="40" y="150" width="820" height="610" fill="#E9EDF7"/><rect x="40" y="150" width="820" height="84" fill="#24305A"/>
<text x="450" y="207" text-anchor="middle" font-family="Unbounded,sans-serif" font-weight="700" font-size="46" fill="#F5F7FF">Mi Local</text>{toldo}
<rect x="90" y="380" width="300" height="240" rx="10" fill="#CFE3FF" stroke="#24305A" stroke-width="10"/>{prods}
<rect x="640" y="380" width="170" height="380" rx="8" fill="#24305A"/><rect x="660" y="400" width="130" height="150" rx="6" fill="#CFE3FF"/><circle cx="785" cy="600" r="8" fill="#F2B705"/>
<rect x="662" y="560" width="126" height="40" rx="6" fill="#22A06B"/><text x="725" y="588" text-anchor="middle" font-family="Outfit,sans-serif" font-weight="700" font-size="24" fill="#fff">ABIERTO</text>
<rect x="0" y="740" width="900" height="20" fill="#4B5778"/></svg>'''
e1 = r.sc(0, 4.7, titulo(.2, f"Camila abrió {Y.format('su local')}") + at(90, 470, .4, local, "fade", .6) + at(390, 860, 1.2, camila("happy"), dur=.6),
          "linear-gradient(#24459E,#0D1838 75%)")

# 2. El negocio creció
papeles = [("Pedidos", 5.9, 110, 560, -12), ("Cuentas", 6.7, 690, 600, 10), ("Inventario", 7.4, 70, 840, 8), ("Fiados", 8.0, 730, 830, -9)]
pp = "".join(f'<div style="position:absolute;left:{x}px;top:{y}px;transform:rotate({rot}deg)"><div class="{r.anim("pop", t, dur=.35)}"><div class="flo" style="animation-delay:-{t % 1.6:.2f}s;background:#FFF7EC;color:#070B18;'
             f'font-family:Unbounded,sans-serif;font-weight:700;font-size:40px;padding:26px 30px;border-radius:10px;box-shadow:0 14px 30px rgba(0,0,0,.4);border-top:14px solid #F2B705">{w}</div></div></div>'
             for w, t, x, y, rot in papeles)
e2 = r.sc(4.7, 9.5, titulo(4.8, f"Pero el negocio {Y.format('creció')}…") + at(390, 620, 5.0, camila("worried"), "pop") + mostrador(60, 960, 1040) + pp)

# 3. Perdió tiempo buscando y viajando
r.n += 1; car = f"k{r.n}"
r.css.append(f"@keyframes {car}{{0%{{transform:translateX(-360px)}}{r.pct(9.8)}{{transform:translateX(-360px)}}{r.pct(14.1)}{{transform:translateX(1180px)}}100%{{transform:translateX(1180px)}}}}"
             f".{car}{{animation:{car} {D}s linear infinite both}}")
auto = ('<svg width="300" height="150" viewBox="0 0 300 150" aria-hidden="true"><path d="M20 90 L60 40 H200 L250 90 H285 V120 H15 V90Z" fill="#E10A3B"/>'
        '<path d="M75 50 H130 V88 H48Z M142 50 H195 L235 88 H142Z" fill="#CFE3FF"/><circle cx="75" cy="122" r="24" fill="#111"/><circle cx="225" cy="122" r="24" fill="#111"/>'
        '<circle cx="75" cy="122" r="10" fill="#A3ADCC"/><circle cx="225" cy="122" r="10" fill="#A3ADCC"/></svg>')
reloj = ('<svg width="230" height="230" viewBox="0 0 230 230" aria-hidden="true"><circle cx="115" cy="115" r="105" fill="#F5F7FF" stroke="#24305A" stroke-width="12"/>'
         + "".join(f'<rect x="111" y="22" width="8" height="18" rx="4" fill="#24305A" transform="rotate({a} 115 115)"/>' for a in range(0, 360, 30))
         + '<rect class="spinfast" x="111" y="45" width="8" height="70" rx="4" fill="#E10A3B"/><rect x="110" y="70" width="10" height="45" rx="5" fill="#070B18" transform="rotate(60 115 115)"/>'
         '<circle cx="115" cy="115" r="10" fill="#070B18"/></svg>')
def letrero(txt):
    return (f'<div style="display:flex;flex-direction:column;align-items:center"><div style="background:#0E7A43;border:6px solid #fff;border-radius:16px;padding:16px 34px;'
            f'font-family:Unbounded,sans-serif;font-weight:700;font-size:44px;color:#fff;white-space:nowrap">{txt} →</div>'
            f'<div style="width:14px;height:150px;background:#A3ADCC"></div></div>')
e3 = r.sc(9.5, 14.2, titulo(9.6, f"Perdió {Y.format('mucho tiempo')} buscando…")
          + at(110, 470, 9.9, reloj, "pop", .4)
          + f'<p class="{r.anim("up", 10.4)}" style="position:absolute;left:380px;top:540px;margin:0;font-family:Unbounded,sans-serif;font-weight:700;font-size:58px;line-height:1.1">Horas<br>y horas…</p>'
          + at(110, 760, 12.2, letrero("Temuco"), "pop", .4)
          + at(560, 760, 13.1, letrero("Santiago"), "pop", .4)
          + '<div style="position:absolute;left:0;right:0;top:1060px;height:210px;background:#2B3352"></div>'
          + '<div class="road" style="position:absolute;left:0;right:0;top:1158px;height:14px;background:repeating-linear-gradient(90deg,#F2B705 0 90px,transparent 90px 160px)"></div>'
          + f'<div style="position:absolute;left:0;top:1030px"><div class="{car}"><div class="bump">{auto}</div></div></div>', "#0B1430")

# 4. Por suerte para ti: SoluTI en Loncoche
pin = ('<svg width="140" height="180" viewBox="0 0 24 31" aria-hidden="true"><path d="M12 30s-10-9.5-10-17a10 10 0 0 1 20 0c0 7.5-10 17-10 17z" fill="#E10A3B"/>'
       '<circle cx="12" cy="13" r="4" fill="#fff"/></svg>')
e4 = r.sc(14.2, 20.4, f'''
<p class="{r.anim('up', 14.5)}" style="position:absolute;left:90px;right:90px;top:240px;margin:0;text-align:center;font-family:Unbounded,sans-serif;font-weight:700;font-size:74px;line-height:1.1;letter-spacing:-1.5px">Pero, por suerte<br>{C.format('para ti…')}</p>
<div style="position:absolute;left:470px;top:520px"><div class="{r.anim('drop', 16.2, dur=.6)}">{pin}</div></div>
<div style="position:absolute;left:0;right:0;top:720px;text-align:center"><span class="{r.anim('pop', 16.7, dur=.4)}" style="display:inline-block;background:#F2B705;color:#070B18;font-weight:700;font-size:42px;padding:14px 32px;border-radius:999px">en Loncoche ahora existe</span></div>
<div style="position:absolute;left:0;right:0;top:850px;text-align:center"><img class="{r.anim('pop', 17.4, dur=.6)}" src="{LOGO}" alt="SoluTI" style="width:760px;height:auto"></div>
<p class="{r.anim('up', 18.3)}" style="position:absolute;left:90px;right:90px;top:1110px;margin:0;text-align:center;font-family:Unbounded,sans-serif;font-weight:700;font-size:54px;line-height:1.15">Soluciones Tecnológicas<br>{C.format('Integrales')}</p>''')

# 5. Cómo trabajamos
pasos = [("chat", "Conversamos contigo", "Nos cuentas cómo funciona tu negocio", 20.9),
         ("doc", "Te enviamos una propuesta", "Qué incluye, plazos y costo", 22.0),
         ("check", "Lo dejamos funcionando", "Computador, tablet o celular", 23.4),
         ("apoyo", "Te seguimos acompañando", "Soporte en la zona y remoto", 24.9)]
filas = ""
for i, (k, t1, t2, t) in enumerate(pasos):
    y = 450 + i * 200
    filas += (f'<div class="{r.anim("right", 20.6 + i * .1, dur=.4)}" style="position:absolute;left:90px;right:90px;top:{y}px;height:170px;box-sizing:border-box;background:#0B1124;border:3px solid #24305A;border-radius:28px;'
              f'display:flex;align-items:center;gap:30px;padding:0 34px">'
              f'<span style="font-family:Unbounded,sans-serif;font-weight:700;font-size:38px;color:#5A6588">0{i + 1}</span>{ic(k)}'
              f'<span style="display:flex;flex-direction:column;gap:6px"><b style="font-size:40px">{t1}</b><span style="font-size:30px;color:#A3ADCC">{t2}</span></span>'
              f'<div class="{r.anim("fade", t, dur=.2)}" style="position:absolute;inset:-3px;border:5px solid #22D3EE;border-radius:28px;box-shadow:0 0 40px rgba(34,211,238,.3)"></div>'
              f'<div class="{r.anim("pop", t, dur=.3)}" style="position:absolute;right:30px;top:53px;width:64px;height:64px;border-radius:50%;background:#22D3EE;display:flex;align-items:center;justify-content:center">'
              f'<svg width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="#070B18" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12.5l5 5L20 6.5"/></svg></div></div>')
e5 = r.sc(20.4, 26.5, titulo(20.5, f"Así {C.format('trabajamos')}") + filas)

# 6. Como Camila, hoy desde el celular
fila = lambda a, b, t: (f'<div class="{r.anim("up", t, dur=.35)}" style="background:#121A33;border-radius:18px;padding:20px 22px;display:flex;flex-direction:column;gap:4px">'
                        f'<span style="font-size:24px;color:#A3ADCC">{a}</span><b style="font-size:36px">{b}</b></div>')
cel = (f'<div style="width:400px;height:760px;box-sizing:border-box;background:#050913;border:12px solid #24305A;border-radius:60px;padding:50px 26px;display:flex;flex-direction:column;gap:18px">'
       f'<span style="font-family:Unbounded,sans-serif;font-weight:700;font-size:32px;text-align:center">Mi Local</span>'
       + fila("Ventas de hoy", "$186.400", 27.6) + fila("Boletas emitidas", "42", 28.3) + fila("Stock bajo", '0 productos <span style="color:#4ADE80">✓</span>', 29.0) + '</div>')
e6 = r.sc(26.5, 31.5, titulo(26.6, f"Como Camila, todo en {C.format('su celular')}")
          + at(40, 640, 26.8, camila("happy"), dur=.6) + mostrador(0, 620, 1060)
          + f'<div style="position:absolute;left:600px;top:520px"><div class="{r.anim("right", 26.9, dur=.6)}"><div style="transform:rotate(6deg)">{cel}</div></div></div>',
          "linear-gradient(#1E3C8C,#0D1838 70%)")

# Cierre
t0 = 31.5
cta = r.sc(t0, None, f'''<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:240px 90px 380px;gap:34px;box-sizing:border-box">
<img class="{r.anim('pop', t0 + .1)}" src="{LOGO}" alt="SoluTI" style="width:460px;height:auto">
<p class="{r.anim('up', t0 + .3)}" style="margin:0;text-align:center;font-family:Unbounded,sans-serif;font-weight:700;font-size:62px;line-height:1.12;letter-spacing:-1.5px">Tecnología de verdad<br>para las {C.format('pymes del sur')}</p>
<div class="{r.anim('pop', 34.3)}" style="background:#22D3EE;color:#070B18;font-family:Unbounded,sans-serif;font-weight:700;font-size:58px;padding:30px 56px;border-radius:26px"><div class="pulse">Comenta HOLA</div></div>
<p class="{r.anim('up', 34.7)}" style="margin:0;font-size:38px;color:#A3ADCC">o escríbenos por WhatsApp</p>
<div class="{r.anim('pop', 34.9)}" style="background:#121A33;border:2px solid #1F2A4D;font-family:Unbounded,sans-serif;font-weight:700;font-size:48px;padding:24px 40px;border-radius:22px;white-space:nowrap">{WSP}</div>
<p class="{r.anim('fade', 35.3)}" style="margin:0;font-size:30px;color:#A3ADCC">SoluTI · Loncoche, La Araucanía</p></div>''')

subs = [(0.4, 4.5, "Camila abrió su propio local en Loncoche, con harto esfuerzo y muchas ganas"),
        (4.8, 9.4, "Pero el negocio creció, y los pedidos, las cuentas y el inventario se le fueron de las manos"),
        (9.7, 14.1, "Perdió mucho tiempo buscando ayuda, y hasta tuvo que viajar a Temuco y a Santiago"),
        (14.5, 20.3, "Pero, por suerte para ti, en Loncoche ahora existe SoluTI, Soluciones Tecnológicas Integrales"),
        (20.7, 26.4, "Conversamos contigo, te enviamos una propuesta, dejamos tu sistema funcionando… y te seguimos acompañando"),
        (26.8, 31.4, "Como Camila, que hoy vende, emite sus boletas y revisa su stock desde el celular")]
sb = "".join(f'<div class="{r.anim("fade", a, b, .2)}" style="position:absolute;left:90px;right:90px;top:1400px;text-align:center"><span style="display:inline-block;background:rgba(5,9,19,.88);'
             f'border-radius:18px;padding:18px 26px;font-size:38px;line-height:1.35;font-weight:600">{txt}</span></div>' for a, b, txt in subs)

html = r.page("Reel quiénes somos Camila", e1 + e2 + e3 + e4 + e5 + e6 + cta + sb)
(proj / "Reel6_Camila.dc.html").write_text(html, encoding="utf-8")
local_out.parent.mkdir(parents=True, exist_ok=True)
local_out.write_text(a_html_local(html), encoding="utf-8")
c = json.loads((proj / "canvas.json").read_text(encoding="utf-8"))
c["boards"]["Reel6_Camila.dc.html"]["title"] = "Reel · Quiénes somos: historia de Camila (38 s)"
(proj / "canvas.json").write_text(json.dumps(c, ensure_ascii=False), encoding="utf-8")
print("ok", D)
