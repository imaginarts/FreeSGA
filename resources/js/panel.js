import './echo';

const root = document.getElementById('panel');
const cfg = {
    url: root.dataset.url,
    channel: root.dataset.channel,
    services: root.dataset.services.split(',').map(Number),
    voice: root.dataset.voice === '1',
    sound: root.dataset.sound === '1',
    timezone: root.dataset.timezone,
};

const $ = (id) => document.getElementById(id);
let lastId = null;
let audio = null;
const announcements = [];
let speaking = false;

// ---------------------------------------------------------------- relógio
function tick() {
    const now = new Date();
    $('clock').textContent = now.toLocaleTimeString('pt-BR', { timeZone: cfg.timezone, hour: '2-digit', minute: '2-digit', second: '2-digit' });
    $('date').textContent = now.toLocaleDateString('pt-BR', { timeZone: cfg.timezone, weekday: 'long', day: 'numeric', month: 'long' });
}
setInterval(tick, 1000);
tick();

// ---------------------------------------------------------------- som e voz
function chime() {
    if (!cfg.sound || !audio) return Promise.resolve();
    const tone = (freq, start, duration, gain) => {
        const o = audio.createOscillator(), g = audio.createGain();
        o.type = 'sine';
        o.frequency.value = freq;
        g.gain.setValueAtTime(gain, audio.currentTime + start);
        g.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + start + duration);
        o.connect(g).connect(audio.destination);
        o.start(audio.currentTime + start);
        o.stop(audio.currentTime + start + duration);
    };
    tone(880, 0, 0.35, 0.4);
    tone(660, 0.3, 0.6, 0.35);
    return new Promise((r) => setTimeout(r, 900));
}

function speak(text) {
    if (!cfg.voice || !('speechSynthesis' in window)) return Promise.resolve();
    return new Promise((resolve) => {
        const u = new SpeechSynthesisUtterance(text);
        u.lang = 'pt-BR';
        u.rate = 0.9;
        const voice = speechSynthesis.getVoices().find((v) => v.lang?.toLowerCase().startsWith('pt'));
        if (voice) u.voice = voice;
        u.onend = u.onerror = () => resolve();
        speechSynthesis.speak(u);
        setTimeout(resolve, 8000);
    });
}

function spoken(call) {
    const letters = call.prefix.split('').join(' ');
    const where = `${call.location} ${call.location_number}`;
    return `Senha ${letters} ${call.number}. ${where}.` + (call.customer_name ? ` ${call.customer_name}.` : '');
}

async function processAnnouncements() {
    if (speaking) return;
    speaking = true;
    while (announcements.length) {
        const call = announcements.shift();
        feature(call);
        await chime();
        await speak(spoken(call));
        await new Promise((r) => setTimeout(r, 600));
    }
    speaking = false;
}

// ---------------------------------------------------------------- render
const pad = (n) => String(n).padStart(2, '0');

function feature(call) {
    const code = $('current-code');
    code.textContent = call.code;
    code.style.color = call.priority ? call.priority_color || '#fca5a5' : '';
    $('current-location').textContent = `${call.location} ${pad(call.location_number)}`;
    $('current-extra').textContent = [call.priority ? call.priority_name : null, call.customer_name, call.service].filter(Boolean).join(' · ');
    const section = code.parentElement;
    section.classList.remove('flash');
    void section.offsetWidth;
    section.classList.add('flash');
}

function renderHistory(calls) {
    const seen = new Set();
    const items = calls.filter((c) => {
        const key = `${c.code}|${c.location}|${c.location_number}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    }).slice(1, 9);

    $('history').innerHTML = items.map((c) => `
        <li class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3">
            <span class="font-mono text-4xl font-bold" style="${c.priority ? `color:${c.priority_color}` : ''}">${c.code}</span>
            <span class="text-right">
                <span class="block text-2xl font-semibold">${escapeHtml(c.location)} ${pad(c.location_number)}</span>
                <span class="block text-sm text-white/60">${escapeHtml(c.service ?? '')}</span>
            </span>
        </li>`).join('') || '<li class="px-2 text-white/40">Sem chamadas</li>';
}

function renderEstimates(items) {
    const list = $('estimates');
    if (!list) return;
    list.innerHTML = items.map((e) => `
        <li class="flex justify-between gap-3">
            <span class="truncate">${escapeHtml(e.service)}</span>
            <span class="font-semibold whitespace-nowrap">${escapeHtml(e.eta)}</span>
        </li>`).join('');
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
}

// ---------------------------------------------------------------- dados
async function refresh() {
    try {
        const res = await fetch(cfg.url, { headers: { Accept: 'application/json' } });
        const { calls, estimates } = await res.json();
        renderEstimates(estimates);
        if (!calls.length) return;

        if (lastId === null) {
            feature(calls[0]);
        } else {
            calls.filter((c) => c.id > lastId).reverse().forEach((c) => announcements.push(c));
            processAnnouncements();
        }
        lastId = calls[0].id;
        renderHistory(calls);
    } catch (e) {
        console.warn('Falha ao atualizar painel', e);
    }
}

$('unlock').addEventListener('click', () => {
    try {
        audio = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {}
    speechSynthesis?.getVoices();
    $('unlock').remove();
    document.documentElement.requestFullscreen?.().catch(() => {});
});

refresh();
// Tempo real via Reverb; o polling cobre quedas de conexão
// a estimativa muda também quando senhas são emitidas
if ($('estimates')) window.Echo?.channel(cfg.channel.replace('.panel', '')).listen('.queue.updated', refresh);
window.Echo?.channel(cfg.channel).listen('.ticket.called', (e) => {
    if (cfg.services.includes(Number(e.service_id))) refresh();
});
setInterval(refresh, 8000);
