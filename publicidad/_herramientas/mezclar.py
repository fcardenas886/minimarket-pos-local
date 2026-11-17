"""Mezcla música + voz (la música baja cuando habla la voz) y la pone sobre el video mudo.
Uso: python mezclar.py video_mudo.mp4 musica.wav salida.mp4 t0:voz0.mp3 t1:voz1.mp3 ...
Si una frase se monta sobre la anterior, se corre hasta que la anterior termine."""
import re, subprocess, sys

video, music, out = sys.argv[1:4]
lines = []
for a in sys.argv[4:]:
    t, f = a.split(":", 1)
    lines.append({"t": float(t), "file": f})


def habla_fin(f):
    e = subprocess.run(["ffmpeg", "-hide_banner", "-i", f, "-af", "silencedetect=noise=-40dB:d=0.3", "-f", "null", "-"],
                       capture_output=True, text=True).stderr
    starts = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", e)]
    dur = float(re.findall(r"Duration: 00:00:([\d.]+)", e)[0])
    return starts[-1] if starts and starts[-1] > .3 else dur


prev = 0
for l in lines:
    t = max(l["t"], prev + .08)
    fin = t + habla_fin(l["file"])
    print(f"{t:6.2f} -> {fin:6.2f}" + (f"  (movida +{t - l['t']:.2f}s)" if t > l["t"] else ""))
    l["t"], prev = t, fin

args = ["ffmpeg", "-loglevel", "error", "-y", "-i", video, "-i", music]
for l in lines:
    args += ["-i", l["file"]]
fc = []
for i, l in enumerate(lines):
    ms = int(l["t"] * 1000)
    fc.append(f"[{i + 2}:a]aformat=sample_rates=44100:channel_layouts=stereo,adelay={ms}|{ms}[v{i}]")
vs = "".join(f"[v{i}]" for i in range(len(lines)))
fc.append(f"{vs}amix=inputs={len(lines)}:normalize=0,highpass=f=90,acompressor=threshold=0.12:ratio=3:attack=5:release=120,volume=1.6,asplit=2[voz][sc]")
fc.append("[1:a]volume=0.55[mus]")
fc.append("[mus][sc]sidechaincompress=threshold=0.03:ratio=6:attack=20:release=350[duck]")
fc.append("[duck][voz]amix=inputs=2:normalize=0,loudnorm=I=-14:TP=-1.5:LRA=9[a]")
args += ["-filter_complex", ";".join(fc), "-map", "0:v", "-map", "[a]", "-c:v", "copy",
         "-c:a", "aac", "-b:a", "192k", "-shortest", "-movflags", "+faststart", out]
subprocess.run(args, check=True)
print("ok", out)
