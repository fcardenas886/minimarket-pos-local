"""Música original sintetizada (libre de derechos): beat electrónico suave, Am-F-C-G, 112 BPM.
Uso: python musica.py salida.wav segundos [semilla]"""
import sys, numpy as np
from scipy.io import wavfile
from scipy.signal import butter, sosfilt

out, L = sys.argv[1], float(sys.argv[2])
seed = int(sys.argv[3]) if len(sys.argv) > 3 else 0
rng = np.random.default_rng(seed)
SR, BPM = 44100, 112
beat = 60 / BPM
N = int(L * SR)
mixL, mixR = np.zeros(N), np.zeros(N)


def hz(m): return 440 * 2 ** ((m - 69) / 12)
def env(n, a, d):
    t = np.arange(n) / SR
    return np.minimum(1, t / max(a, 1e-4)) * np.exp(-t / d)
def add(sig, t, g=1.0, pan=0.0):
    i = int(t * SR)
    if i >= N: return
    s = sig[: N - i] * g
    mixL[i:i + len(s)] += s * (1 - pan) ** .5 if pan > 0 else s
    mixR[i:i + len(s)] += s * (1 + pan) ** .5 if pan < 0 else s
def filt(x, kind, f, order=2):
    return sosfilt(butter(order, f, kind, fs=SR, output="sos"), x)
def saw(f, n, det=0.0):
    t = np.arange(n) / SR
    return sum(np.sin(2 * np.pi * f * (1 + det) * k * t) / k for k in range(1, 9))

kn = int(.45 * SR); tk = np.arange(kn) / SR
kick = np.sin(2 * np.pi * (45 * tk + (110 / 18) * (1 - np.exp(-tk * 18)))) * np.exp(-tk * 7)
hat = filt(rng.standard_normal(int(.06 * SR)), "highpass", 7000) * env(int(.06 * SR), .001, .018)
clap = filt(rng.standard_normal(int(.25 * SR)), "bandpass", [900, 3500]) * env(int(.25 * SR), .002, .07)

prog = [(57, [57, 60, 64]), (53, [53, 57, 60]), (48, [48, 52, 55, 60]), (55, [55, 59, 62])]
bar = 4 * beat
for b in range(int(np.ceil(L / bar))):
    t0 = b * bar
    root, chord = prog[b % 4]
    intro = b == 0
    pn = int(bar * SR) + int(.3 * SR)
    pad = sum(saw(hz(m + 12), pn, d) for m in chord for d in (-.004, .004))
    pad = filt(pad, "lowpass", 1400) * np.minimum(1, np.arange(pn) / (.35 * SR)) * np.minimum(1, (pn - np.arange(pn)) / (.3 * SR))
    add(pad / 14, t0, .55, -.3); add(pad / 14, t0, .55, .3)
    for s in range(8):
        ts = t0 + s * beat / 2
        if not intro:
            if s % 2 == 0: add(kick, ts, .95)
            add(hat, ts + beat / 4, .22 + .08 * (s % 2), .4)
            if s in (2, 6): add(clap, ts, .5, -.1)
        bn = int(beat / 2 * SR)
        add(filt(saw(hz(root - 24 + (12 if s in (3, 7) else 0)), bn), "lowpass", 380) * env(bn, .005, .16), ts, .45 if not intro else .25)
    for s in range(16):
        m = chord[[0, 1, 2, 1][s % 4]] + 24 if s % 8 < 6 else chord[-1] + 24
        pn2 = int(.25 * SR)
        add(filt(saw(hz(m), pn2), "lowpass", 2600) * env(pn2, .002, .06), t0 + s * beat / 4, .09, .5 if s % 2 else -.5)

mix = np.stack([mixL, mixR], 1)
fade = int(1.6 * SR)
mix[-fade:] *= np.linspace(1, 0, fade)[:, None]
mix[: int(.05 * SR)] *= np.linspace(0, 1, int(.05 * SR))[:, None]
mix = np.tanh(mix / np.abs(mix).max() * 1.4) * .8
wavfile.write(out, SR, (mix * 32767).astype(np.int16))
print("ok", out, L)
