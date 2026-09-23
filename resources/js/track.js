import './echo';

const root = document.getElementById('track');
const $ = (id) => document.getElementById(id);
let audio = null;
let lastStatus = null;
let alertedNear = false;

const STATUS = {
    issued: { text: 'Aguardando chamada', cls: 'bg-slate-50 text-slate-700' },
    called: { text: 'Sua senha foi chamada!', cls: 'bg-emerald-500 text-white called' },
    started: { text: 'Em atendimento', cls: 'bg-blue-50 text-blue-700' },
    finished: { text: 'Atendimento encerrado. Obrigado!', cls: 'bg-emerald-50 text-emerald-700' },
    no_show: { text: 'Senha não compareceu. Procure a recepção.', cls: 'bg-orange-50 text-orange-700' },
    cancelled: { text: 'Senha cancelada. Procure a recepção.', cls: 'bg-red-50 text-red-700' },
    expired: { text: 'Senha expirada. Procure a recepção.', cls: 'bg-red-50 text-red-700' },
};

function beep(times = 3) {
    if (!audio) return;
    for (let i = 0; i < times; i++) {
        const o = audio.createOscillator(), g = audio.createGain();
        const t = audio.currentTime + i * 0.45;
        o.frequency.value = 880;
        g.gain.setValueAtTime(0.4, t);
        g.gain.exponentialRampToValueAtTime(0.001, t + 0.35);
        o.connect(g).connect(audio.destination);
        o.start(t);
        o.stop(t + 0.35);
    }
}

function alertUser(title, body) {
    beep();
    navigator.vibrate?.([300, 150, 300, 150, 300]);
    if (window.Notification?.permission === 'granted' && document.hidden) {
        new Notification(title, { body, tag: 'sga-ticket', renotify: true });
    }
}

function render(d) {
    $('code').textContent = d.code;
    $('service').textContent = d.service;

    const s = STATUS[d.status] ?? { text: d.status_label, cls: 'bg-slate-50 text-slate-700' };
    const box = $('status');
    box.className = `mt-5 rounded-2xl px-4 py-4 text-lg font-semibold ${s.cls}`;
    box.innerHTML = '';
    box.append(s.text);
    if (d.status === 'called' && d.location) {
        const loc = document.createElement('span');
        loc.className = 'mt-1 block text-3xl';
        loc.textContent = `Dirija-se ao ${d.location}`;
        box.append(loc);
    } else if (d.status === 'issued' && d.near) {
        box.className = 'mt-5 rounded-2xl bg-amber-100 px-4 py-4 text-lg font-semibold text-amber-800';
        box.textContent = 'Você é um dos próximos! Fique atento.';
    }

    $('survey').classList.toggle('hidden', !d.survey_url);
    if (d.survey_url) $('survey').href = d.survey_url;

    $('position-box').classList.toggle('hidden', d.position == null);
    $('position').textContent = d.position ?? '–';
    $('eta-box').classList.toggle('hidden', d.eta_text == null);
    $('eta').textContent = d.eta_text ?? '–';
    $('stats').classList.toggle('hidden', d.position == null && d.eta_text == null);

    $('calls-box').classList.toggle('hidden', !d.last_calls.length || d.status !== 'issued');
    $('calls').innerHTML = '';
    d.last_calls.forEach((c) => {
        const li = document.createElement('li');
        li.className = 'flex justify-between py-2';
        const code = document.createElement('span');
        code.className = 'font-mono font-bold';
        code.textContent = c.code;
        const where = document.createElement('span');
        where.className = 'text-slate-500';
        where.textContent = c.location;
        li.append(code, where);
        $('calls').append(li);
    });

    if (lastStatus !== null && d.status === 'called' && lastStatus !== 'called') {
        alertUser(`Senha ${d.code} chamada!`, d.location ? `Dirija-se ao ${d.location}` : '');
    } else if (d.near && !alertedNear && d.status === 'issued') {
        alertedNear = true;
        if (lastStatus !== null) alertUser('Sua vez está chegando', `Senha ${d.code}: você é um dos próximos.`);
    }
    lastStatus = d.status;
}

async function refresh() {
    try {
        const res = await fetch(root.dataset.url, { headers: { Accept: 'application/json' } });
        if (res.ok) render(await res.json());
    } catch (e) {
        console.warn(e);
    }
}

$('notify').classList.remove('hidden');
$('notify').addEventListener('click', async () => {
    try {
        audio = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {}
    if (window.Notification && Notification.permission === 'default') await Notification.requestPermission();
    beep(1);
    $('notify').textContent = 'Avisos ativados ✓';
    $('notify').disabled = true;
});

refresh();
window.Echo?.channel(root.dataset.channel).listen('.queue.updated', refresh);
setInterval(refresh, 15000);
document.addEventListener('visibilitychange', () => !document.hidden && refresh());
