const STORAGE_TOPICS_KEY = 'svoyak_topics_tv_v1';
const STORAGE_TEAMS_KEY = 'svoyak_teams_tv_v1';
const STORAGE_USED_KEY = 'svoyak_used_tv_v1';

const state = {
  topics: [],
  teams: [],
  usedCells: new Set(),
  selectedTopicIds: new Set(),
  editingId: null,
  currentQuestion: null,
  currentQuizTopics: [],
  timer: null,
  timerValue: 30,
};

const screens = {
  home: document.getElementById('homeScreen'),
  add: document.getElementById('addTopicScreen'),
  select: document.getElementById('selectTopicsScreen'),
  quiz: document.getElementById('quizScreen'),
};

const el = {
  homeStats: document.getElementById('homeStats'),
  topicForm: document.getElementById('topicForm'),
  topicStatus: document.getElementById('topicStatus'),
  themeSearch: document.getElementById('themeSearch'),
  topicsList: document.getElementById('topicsList'),
  topicsCounter: document.getElementById('topicsCounter'),
  selectStatus: document.getElementById('selectStatus'),
  saveTopicBtn: document.getElementById('saveTopicBtn'),
  cancelEditBtn: document.getElementById('cancelEditBtn'),
  quizBoard: document.getElementById('quizBoard'),
  quizInfo: document.getElementById('quizInfo'),
  teamsList: document.getElementById('teamsList'),
  overlay: document.getElementById('questionOverlay'),
  modalTheme: document.getElementById('modalTheme'),
  modalPoints: document.getElementById('modalPoints'),
  modalQuestion: document.getElementById('modalQuestion'),
  modalAnswer: document.getElementById('modalAnswer'),
  answerBox: document.getElementById('answerBox'),
  awardButtons: document.getElementById('awardButtons'),
  timerValue: document.getElementById('timerValue'),
};

const fields = {
  theme_name: document.getElementById('theme_name'),
  q100: document.getElementById('q100'),
  a100: document.getElementById('a100'),
  q200: document.getElementById('q200'),
  a200: document.getElementById('a200'),
  q300: document.getElementById('q300'),
  a300: document.getElementById('a300'),
  q400: document.getElementById('q400'),
  a400: document.getElementById('a400'),
  q500: document.getElementById('q500'),
  a500: document.getElementById('a500'),
};

function safeParse(raw, fallback) {
  try {
    const parsed = JSON.parse(raw);
    return parsed ?? fallback;
  } catch {
    return fallback;
  }
}

function seedTopics() {
  return [
    {
      id: 1,
      theme_name: 'Космос',
      q100: 'Как называется наша галактика?',
      a100: 'Млечный Путь',
      q200: 'Первая планета от Солнца?',
      a200: 'Меркурий',
      q300: 'Кто был первым космонавтом?',
      a300: 'Юрий Гагарин',
      q400: 'Естественный спутник Земли?',
      a400: 'Луна',
      q500: 'Какую планету называют красной?',
      a500: 'Марс',
    },
    {
      id: 2,
      theme_name: 'Кино',
      q100: 'Школа магии из Гарри Поттера?',
      a100: 'Хогвартс',
      q200: 'Кто снял Титаник?',
      a200: 'Джеймс Кэмерон',
      q300: 'Как зовут зелёного великана из мультфильма DreamWorks?',
      a300: 'Шрек',
      q400: 'Кто сыграл Железного человека?',
      a400: 'Роберт Дауни-младший',
      q500: 'Как зовут льва из Короля Льва, который становится королём?',
      a500: 'Симба',
    },
  ];
}

function seedTeams() {
  return [
    { id: 1, name: 'Команда 1', score: 0 },
    { id: 2, name: 'Команда 2', score: 0 },
  ];
}

function loadState() {
  state.topics = safeParse(localStorage.getItem(STORAGE_TOPICS_KEY), []);
  state.teams = safeParse(localStorage.getItem(STORAGE_TEAMS_KEY), []);
  state.usedCells = new Set(safeParse(localStorage.getItem(STORAGE_USED_KEY), []));

  if (!Array.isArray(state.topics) || state.topics.length === 0) {
    state.topics = seedTopics();
    saveTopics();
  }
  if (!Array.isArray(state.teams) || state.teams.length === 0) {
    state.teams = seedTeams();
    saveTeams();
  }
}

function saveTopics() {
  localStorage.setItem(STORAGE_TOPICS_KEY, JSON.stringify(state.topics));
}

function saveTeams() {
  localStorage.setItem(STORAGE_TEAMS_KEY, JSON.stringify(state.teams));
}

function saveUsed() {
  localStorage.setItem(STORAGE_USED_KEY, JSON.stringify([...state.usedCells]));
}

function switchScreen(name) {
  Object.values(screens).forEach(s => s.classList.remove('active'));
  screens[name].classList.add('active');
  renderHomeStats();
}

function renderHomeStats() {
  el.homeStats.textContent = `Тем: ${state.topics.length} · Команд: ${state.teams.length}`;
}

function setStatus(node, text = '', type = '') {
  node.textContent = text;
  node.className = 'status' + (type ? ' ' + type : '');
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function shorten(text, max) {
  const value = String(text || '');
  return value.length > max ? value.slice(0, max - 1) + '…' : value;
}

function getNextTopicId() {
  const ids = state.topics.map(t => Number(t.id) || 0);
  return ids.length ? Math.max(...ids) + 1 : 1;
}

function getNextTeamId() {
  const ids = state.teams.map(t => Number(t.id) || 0);
  return ids.length ? Math.max(...ids) + 1 : 1;
}

function getFormTopic() {
  const topic = {
    theme_name: fields.theme_name.value.trim(),
    q100: fields.q100.value.trim(),
    a100: fields.a100.value.trim(),
    q200: fields.q200.value.trim(),
    a200: fields.a200.value.trim(),
    q300: fields.q300.value.trim(),
    a300: fields.a300.value.trim(),
    q400: fields.q400.value.trim(),
    a400: fields.a400.value.trim(),
    q500: fields.q500.value.trim(),
    a500: fields.a500.value.trim(),
  };
  if (state.editingId != null) topic.id = state.editingId;
  return topic;
}

function validateTopic(topic) {
  const keys = ['theme_name', 'q100','a100','q200','a200','q300','a300','q400','a400','q500','a500'];
  return keys.every(k => String(topic[k] || '').trim());
}

function resetForm() {
  state.editingId = null;
  el.topicForm.reset();
  el.saveTopicBtn.textContent = 'Сохранить тему';
  el.cancelEditBtn.hidden = true;
}

function fillForm(topic) {
  fields.theme_name.value = topic.theme_name;
  fields.q100.value = topic.q100;
  fields.a100.value = topic.a100;
  fields.q200.value = topic.q200;
  fields.a200.value = topic.a200;
  fields.q300.value = topic.q300;
  fields.a300.value = topic.a300;
  fields.q400.value = topic.q400;
  fields.a400.value = topic.a400;
  fields.q500.value = topic.q500;
  fields.a500.value = topic.a500;
}

function renderTopicsSelection() {
  const q = el.themeSearch.value.trim().toLowerCase();
  const filtered = state.topics
    .filter(t => t.theme_name.toLowerCase().includes(q))
    .sort((a,b) => a.theme_name.localeCompare(b.theme_name, 'ru'));

  el.topicsCounter.textContent = `Всего тем: ${state.topics.length} · Отмечено: ${state.selectedTopicIds.size}`;
  el.topicsList.innerHTML = '';

  if (!filtered.length) {
    el.topicsList.innerHTML = '<div class="muted">Темы не найдены.</div>';
    return;
  }

  for (const topic of filtered) {
    const checked = state.selectedTopicIds.has(topic.id) ? 'checked' : '';
    const card = document.createElement('div');
    card.className = 'topic-card';
    card.innerHTML = `
      <div class="topic-top">
        <div class="topic-title">
          <input type="checkbox" data-check-id="${topic.id}" ${checked} />
          <div style="font-size:18px;font-weight:900;">${escapeHtml(topic.theme_name)}</div>
          <span class="tag">5 вопросов</span>
        </div>
        <div class="row">
          <button class="btn btn-secondary" data-edit-id="${topic.id}">Изменить</button>
          <button class="btn btn-danger" data-delete-id="${topic.id}">Удалить</button>
        </div>
      </div>
      <div class="preview-grid">
        <div class="preview-item"><strong>100</strong>${escapeHtml(shorten(topic.q100, 60))}</div>
        <div class="preview-item"><strong>200</strong>${escapeHtml(shorten(topic.q200, 60))}</div>
        <div class="preview-item"><strong>300</strong>${escapeHtml(shorten(topic.q300, 60))}</div>
        <div class="preview-item"><strong>400</strong>${escapeHtml(shorten(topic.q400, 60))}</div>
        <div class="preview-item"><strong>500</strong>${escapeHtml(shorten(topic.q500, 60))}</div>
      </div>
    `;
    el.topicsList.appendChild(card);
  }

  el.topicsList.querySelectorAll('[data-check-id]').forEach(box => {
    box.addEventListener('change', e => {
      const id = Number(e.target.getAttribute('data-check-id'));
      if (e.target.checked) state.selectedTopicIds.add(id);
      else state.selectedTopicIds.delete(id);
      el.topicsCounter.textContent = `Всего тем: ${state.topics.length} · Отмечено: ${state.selectedTopicIds.size}`;
    });
  });

  el.topicsList.querySelectorAll('[data-edit-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = Number(btn.getAttribute('data-edit-id'));
      const topic = state.topics.find(t => t.id === id);
      if (!topic) return;
      state.editingId = id;
      fillForm(topic);
      el.saveTopicBtn.textContent = 'Сохранить изменения';
      el.cancelEditBtn.hidden = false;
      setStatus(el.topicStatus, 'Режим редактирования включён.', 'ok');
      switchScreen('add');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  el.topicsList.querySelectorAll('[data-delete-id]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = Number(btn.getAttribute('data-delete-id'));
      if (!confirm('Удалить тему?')) return;
      state.topics = state.topics.filter(t => t.id !== id);
      state.selectedTopicIds.delete(id);
      [100,200,300,400,500].forEach(p => state.usedCells.delete(`${id}_${p}`));
      saveTopics();
      saveUsed();
      renderTopicsSelection();
      renderHomeStats();
      setStatus(el.selectStatus, 'Тема удалена.', 'ok');
    });
  });
}

function renderTeams() {
  el.teamsList.innerHTML = '';
  const sorted = [...state.teams].sort((a,b) => b.score - a.score);

  for (const team of sorted) {
    const card = document.createElement('div');
    card.className = 'team-card';
    card.innerHTML = `
      <div class="team-name">${escapeHtml(team.name)}</div>
      <div class="team-score">${team.score}</div>
      <div class="row">
        <button class="btn btn-secondary" data-plus="${team.id}">+100</button>
        <button class="btn btn-secondary" data-minus="${team.id}">-100</button>
      </div>
      <button class="btn btn-danger" data-delete-team="${team.id}">Удалить</button>
    `;
    el.teamsList.appendChild(card);
  }

  el.teamsList.querySelectorAll('[data-plus]').forEach(btn => {
    btn.addEventListener('click', () => changeTeamScore(Number(btn.dataset.plus), 100));
  });
  el.teamsList.querySelectorAll('[data-minus]').forEach(btn => {
    btn.addEventListener('click', () => changeTeamScore(Number(btn.dataset.minus), -100));
  });
  el.teamsList.querySelectorAll('[data-delete-team]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = Number(btn.dataset.deleteTeam);
      if (!confirm('Удалить команду?')) return;
      state.teams = state.teams.filter(t => t.id !== id);
      saveTeams();
      renderTeams();
      renderAwardButtons();
    });
  });
}

function changeTeamScore(id, delta) {
  const team = state.teams.find(t => t.id === id);
  if (!team) return;
  team.score += delta;
  saveTeams();
  renderTeams();
  renderAwardButtons();
  playScoreSound(delta > 0);
}

function buildQuiz() {
  const selected = state.topics.filter(t => state.selectedTopicIds.has(t.id));
  state.currentQuizTopics = selected;

  if (!selected.length) {
    setStatus(el.selectStatus, 'Выбери хотя бы одну тему.', 'error');
    return;
  }

  const cols = selected.length;
  const points = [100,200,300,400,500];
  el.quizBoard.innerHTML = '';
  el.quizInfo.textContent = `Тем в игре: ${selected.length}`;

  const head = document.createElement('div');
  head.className = 'quiz-head';
  head.style.gridTemplateColumns = `repeat(${cols}, minmax(140px, 1fr))`;
  selected.forEach(topic => {
    const node = document.createElement('div');
    node.className = 'cell header';
    node.textContent = topic.theme_name;
    head.appendChild(node);
  });
  el.quizBoard.appendChild(head);

  points.forEach(point => {
    const row = document.createElement('div');
    row.className = 'quiz-row';
    row.style.gridTemplateColumns = `repeat(${cols}, minmax(140px, 1fr))`;
    selected.forEach(topic => {
      const key = `${topic.id}_${point}`;
      const btn = document.createElement('button');
      btn.className = 'cell question' + (state.usedCells.has(key) ? ' used' : '');
      btn.textContent = point;
      btn.addEventListener('click', () => openQuestion(topic, point));
      row.appendChild(btn);
    });
    el.quizBoard.appendChild(row);
  });

  renderTeams();
  switchScreen('quiz');
  setStatus(el.selectStatus, '');
}

function openQuestion(topic, points) {
  state.currentQuestion = {
    topicId: topic.id,
    theme_name: topic.theme_name,
    points,
    question: topic[`q${points}`],
    answer: topic[`a${points}`],
  };
  el.modalTheme.textContent = topic.theme_name;
  el.modalPoints.textContent = `${points} очков`;
  el.modalQuestion.textContent = state.currentQuestion.question;
  el.modalAnswer.textContent = state.currentQuestion.answer;
  el.answerBox.classList.remove('open');
  el.overlay.classList.add('open');
  resetTimerDisplay();
  renderAwardButtons();
  playOpenSound();
}

function closeQuestion() {
  stopTimer();
  el.overlay.classList.remove('open');
  state.currentQuestion = null;
}

function markCurrentQuestionUsed() {
  if (!state.currentQuestion) return;
  state.usedCells.add(`${state.currentQuestion.topicId}_${state.currentQuestion.points}`);
  saveUsed();
  closeQuestion();
  buildQuiz();
}

function renderAwardButtons() {
  el.awardButtons.innerHTML = '';
  if (!state.currentQuestion) return;

  state.teams.forEach(team => {
    const btn = document.createElement('button');
    btn.className = 'btn btn-secondary';
    btn.textContent = `${team.name} +${state.currentQuestion.points}`;
    btn.addEventListener('click', () => changeTeamScore(team.id, state.currentQuestion.points));
    el.awardButtons.appendChild(btn);
  });
}

function addTeam() {
  const name = prompt('Название команды:');
  if (!name || !name.trim()) return;
  state.teams.push({ id: getNextTeamId(), name: name.trim(), score: 0 });
  saveTeams();
  renderTeams();
  renderHomeStats();
}

function resetScores() {
  state.teams.forEach(t => t.score = 0);
  saveTeams();
  renderTeams();
  renderAwardButtons();
}

function exportTopicsJson() {
  const data = JSON.stringify(state.topics, null, 2);
  const blob = new Blob([data], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'svoyak_topics.json';
  a.click();
  URL.revokeObjectURL(url);
}

function importTopicsJson(file) {
  const reader = new FileReader();
  reader.onload = () => {
    try {
      const parsed = JSON.parse(reader.result);
      if (!Array.isArray(parsed)) throw new Error('JSON должен содержать массив тем.');
      let nextId = getNextTopicId();
      for (const item of parsed) {
        const topic = {
          id: nextId++,
          theme_name: String(item.theme_name || '').trim(),
          q100: String(item.q100 || '').trim(),
          a100: String(item.a100 || '').trim(),
          q200: String(item.q200 || '').trim(),
          a200: String(item.a200 || '').trim(),
          q300: String(item.q300 || '').trim(),
          a300: String(item.a300 || '').trim(),
          q400: String(item.q400 || '').trim(),
          a400: String(item.a400 || '').trim(),
          q500: String(item.q500 || '').trim(),
          a500: String(item.a500 || '').trim(),
        };
        if (!validateTopic(topic)) throw new Error('В JSON найдены пустые поля.');
        state.topics.push(topic);
      }
      saveTopics();
      renderTopicsSelection();
      renderHomeStats();
      setStatus(el.selectStatus, 'Импорт завершён.', 'ok');
    } catch (err) {
      setStatus(el.selectStatus, err.message || 'Ошибка импорта.', 'error');
    }
  };
  reader.readAsText(file, 'utf-8');
}

function resetTimerDisplay() {
  state.timerValue = 30;
  el.timerValue.textContent = '30';
  el.timerValue.classList.remove('danger');
}

function startTimer() {
  stopTimer();
  playStartTimerSound();
  state.timerValue = 30;
  el.timerValue.textContent = String(state.timerValue);
  el.timerValue.classList.remove('danger');

  state.timer = setInterval(() => {
    state.timerValue -= 1;
    el.timerValue.textContent = String(state.timerValue);

    if (state.timerValue <= 5) {
      el.timerValue.classList.add('danger');
      playTickSound();
    }

    if (state.timerValue <= 0) {
      stopTimer();
      playTimeoutSound();
    }
  }, 1000);
}

function stopTimer() {
  if (state.timer) {
    clearInterval(state.timer);
    state.timer = null;
  }
}

function audioCtx() {
  const Ctx = window.AudioContext || window.webkitAudioContext;
  if (!Ctx) return null;
  if (!window.__svAudioCtx) window.__svAudioCtx = new Ctx();
  return window.__svAudioCtx;
}

function beep(freq = 440, duration = 0.12, type = 'sine', gainValue = 0.04) {
  const ctx = audioCtx();
  if (!ctx) return;
  const osc = ctx.createOscillator();
  const gain = ctx.createGain();
  osc.type = type;
  osc.frequency.value = freq;
  gain.gain.value = gainValue;
  osc.connect(gain);
  gain.connect(ctx.destination);
  osc.start();
  gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);
  osc.stop(ctx.currentTime + duration);
}

function playOpenSound() {
  beep(480, 0.08, 'triangle', 0.05);
  setTimeout(() => beep(720, 0.1, 'triangle', 0.05), 90);
}
function playStartTimerSound() {
  beep(660, 0.08, 'square', 0.04);
  setTimeout(() => beep(880, 0.08, 'square', 0.04), 90);
}
function playTickSound() {
  beep(1000, 0.04, 'square', 0.03);
}
function playTimeoutSound() {
  beep(240, 0.20, 'sawtooth', 0.05);
  setTimeout(() => beep(180, 0.24, 'sawtooth', 0.05), 170);
}
function playScoreSound(up) {
  if (up) {
    beep(700, 0.08, 'triangle', 0.04);
    setTimeout(() => beep(980, 0.12, 'triangle', 0.04), 80);
  } else {
    beep(360, 0.12, 'square', 0.04);
  }
}

el.topicForm.addEventListener('submit', e => {
  e.preventDefault();
  const topic = getFormTopic();

  if (!validateTopic(topic)) {
    setStatus(el.topicStatus, 'Заполни все вопросы и ответы от 100 до 500.', 'error');
    return;
  }

  const duplicate = state.topics.find(t =>
    t.theme_name.toLowerCase() === topic.theme_name.toLowerCase() &&
    t.id !== state.editingId
  );
  if (duplicate) {
    setStatus(el.topicStatus, 'Тема с таким названием уже есть.', 'error');
    return;
  }

  if (state.editingId != null) {
    const idx = state.topics.findIndex(t => t.id === state.editingId);
    if (idx !== -1) state.topics[idx] = { ...topic, id: state.editingId };
    setStatus(el.topicStatus, 'Тема обновлена.', 'ok');
  } else {
    topic.id = getNextTopicId();
    state.topics.push(topic);
    setStatus(el.topicStatus, 'Тема сохранена.', 'ok');
  }

  saveTopics();
  resetForm();
  renderTopicsSelection();
  renderHomeStats();
});

el.cancelEditBtn.addEventListener('click', () => {
  resetForm();
  setStatus(el.topicStatus, 'Редактирование отменено.');
});

document.getElementById('goCreateQuiz').addEventListener('click', () => {
  renderTopicsSelection();
  switchScreen('select');
});
document.getElementById('goAddTopic').addEventListener('click', () => {
  resetForm();
  switchScreen('add');
});
document.getElementById('goHomeBtn').addEventListener('click', () => switchScreen('home'));
document.querySelectorAll('.back-home').forEach(btn => btn.addEventListener('click', () => switchScreen('home')));
document.getElementById('backToSelectBtn').addEventListener('click', () => {
  renderTopicsSelection();
  switchScreen('select');
});

el.themeSearch.addEventListener('input', renderTopicsSelection);
document.getElementById('buildQuizBtn').addEventListener('click', buildQuiz);
document.getElementById('addTeamBtn').addEventListener('click', addTeam);
document.getElementById('resetScoresBtn').addEventListener('click', resetScores);
document.getElementById('exportJsonBtn').addEventListener('click', exportTopicsJson);
document.getElementById('importJsonInput').addEventListener('change', e => {
  const file = e.target.files?.[0];
  if (file) importTopicsJson(file);
  e.target.value = '';
});

document.getElementById('resetUsedBtn').addEventListener('click', () => {
  state.usedCells.clear();
  saveUsed();
  if (state.currentQuizTopics.length) buildQuiz();
});

document.getElementById('showAnswerBtn').addEventListener('click', () => {
  el.answerBox.classList.add('open');
});
document.getElementById('markUsedBtn').addEventListener('click', markCurrentQuestionUsed);
document.getElementById('closeModalBtn').addEventListener('click', closeQuestion);
document.getElementById('startTimerBtn').addEventListener('click', startTimer);
document.getElementById('stopTimerBtn').addEventListener('click', stopTimer);

el.overlay.addEventListener('click', e => {
  if (e.target === el.overlay) closeQuestion();
});

loadState();
renderHomeStats();
renderTopicsSelection();
renderTeams();
switchScreen('home');
