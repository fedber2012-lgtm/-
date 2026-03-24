<?php
session_start();
$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Свояк — Lobby Edition</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="app">
    <header class="topbar">
      <div>
        <h1>Свояк — Lobby Edition</h1>
        <div class="muted">Лобби, телефоны-кнопки, общий банк тем, админка</div>
      </div>
      <div class="row">
        <button class="btn secondary" id="homeBtn">Главная</button>
        <button class="btn secondary" id="loginBtn">Войти</button>
        <button class="btn secondary hidden" id="logoutBtn">Выйти</button>
        <button class="btn secondary hidden" id="adminBtn">Админка</button>
      </div>
    </header>

    <main>
      <section id="homeScreen" class="screen active card">
        <div class="card-head">
          <h2>Главное меню</h2>
          <div id="authInfo" class="muted"></div>
        </div>
        <div class="card-body">
          <div id="desktopHome" class="home-grid">
            <button class="big-btn gold" id="goThemeBankBtn">Банк тем</button>
            <button class="big-btn blue" id="goCreateLobbyBtn">Создать игру</button>
            <button class="big-btn dark" id="goJoinLobbyBtn">Присоединиться</button>
          </div>
          <div id="mobileHome" class="mobile-home hidden">
            <button class="big-btn danger" id="goJoinLobbyBtnMobile">Присоединиться</button>
          </div>
        </div>
      </section>

      <section id="themeBankScreen" class="screen card">
        <div class="card-head">
          <h2>Банк тем</h2>
          <div class="row">
            <button class="btn secondary" id="refreshThemesBtn">Обновить</button>
            <button class="btn secondary back-home">Назад</button>
          </div>
        </div>
        <div class="card-body">
          <div class="toolbar">
            <input id="themeSearch" class="input" placeholder="Поиск тем" />
          </div>

          <div id="createThemeWrap" class="card inner-card">
            <div class="inner-head">
              <h3>Создать тему</h3>
              <div id="themeFormInfo" class="muted">Для создания темы нужен вход в аккаунт</div>
            </div>
            <form id="themeForm" class="theme-form">
              <div class="field full">
                <label for="theme_name">Название темы</label>
                <input id="theme_name" required />
              </div>

              <div class="qa-grid">
                <div class="cost">100</div>
                <div class="field"><label for="q100">Вопрос за 100</label><textarea id="q100" required></textarea></div>
                <div class="field"><label for="a100">Ответ за 100</label><textarea id="a100" required></textarea></div>

                <div class="cost">200</div>
                <div class="field"><label for="q200">Вопрос за 200</label><textarea id="q200" required></textarea></div>
                <div class="field"><label for="a200">Ответ за 200</label><textarea id="a200" required></textarea></div>

                <div class="cost">300</div>
                <div class="field"><label for="q300">Вопрос за 300</label><textarea id="q300" required></textarea></div>
                <div class="field"><label for="a300">Ответ за 300</label><textarea id="a300" required></textarea></div>

                <div class="cost">400</div>
                <div class="field"><label for="q400">Вопрос за 400</label><textarea id="q400" required></textarea></div>
                <div class="field"><label for="a400">Ответ за 400</label><textarea id="a400" required></textarea></div>

                <div class="cost">500</div>
                <div class="field"><label for="q500">Вопрос за 500</label><textarea id="q500" required></textarea></div>
                <div class="field"><label for="a500">Ответ за 500</label><textarea id="a500" required></textarea></div>
              </div>

              <div class="row">
                <button class="btn gold" type="submit">Сохранить тему</button>
              </div>
              <div id="themeStatus" class="status"></div>
            </form>
          </div>

          <div class="card inner-card mt16">
            <div class="inner-head">
              <h3>Все темы</h3>
              <div id="themesCount" class="muted"></div>
            </div>
            <div id="themesList" class="topics-list"></div>
          </div>
        </div>
      </section>

      <section id="createLobbyScreen" class="screen card">
        <div class="card-head">
          <h2>Создать игру</h2>
          <button class="btn secondary back-home">Назад</button>
        </div>
        <div class="card-body">
          <div class="toolbar">
            <input id="lobbyThemeSearch" class="input" placeholder="Поиск тем для игры" />
          </div>
          <div id="lobbyThemesList" class="topics-list"></div>
          <div class="row mt16">
            <button class="btn gold" id="createLobbyConfirmBtn">Создать лобби</button>
          </div>
          <div id="lobbyCreateStatus" class="status"></div>
        </div>
      </section>

      <section id="lobbyWaitingScreen" class="screen card">
        <div class="card-head">
          <h2>Лобби</h2>
          <div class="row">
            <div class="pill">Код: <span id="lobbyCodeText"></span></div>
            <button class="btn gold" id="startGameBtn">Начать игру</button>
          </div>
        </div>
        <div class="card-body">
          <div class="muted">Участники могут зайти с телефона по коду лобби.</div>
          <div id="lobbyPlayers" class="players-grid mt16"></div>
        </div>
      </section>

      <section id="joinLobbyScreen" class="screen card">
        <div class="card-head">
          <h2>Присоединиться</h2>
          <button class="btn secondary back-home">Назад</button>
        </div>
        <div class="card-body">
          <form id="joinLobbyForm" class="join-form">
            <div class="field"><label for="joinCode">Код лобби</label><input id="joinCode" required /></div>
            <div class="field"><label for="joinNick">Ник</label><input id="joinNick" required /></div>
            <div class="field">
              <label for="joinAvatar">Аватарка</label>
              <select id="joinAvatar">
                <option>😀</option><option>😎</option><option>🤖</option><option>🐯</option>
                <option>🦊</option><option>🐼</option><option>🦁</option><option>🐸</option>
              </select>
            </div>
            <div class="row"><button class="btn gold" type="submit">Войти в лобби</button></div>
            <div id="joinStatus" class="status"></div>
          </form>
        </div>
      </section>

      <section id="playerLobbyScreen" class="screen card">
        <div class="card-head">
          <h2>Лобби игрока</h2>
          <div class="pill">Код: <span id="playerLobbyCode"></span></div>
        </div>
        <div class="card-body">
          <div id="playerWaitingInfo" class="muted">Ожидание начала игры...</div>
          <div id="playerScoreboard" class="players-grid mt16"></div>
          <div id="playerBuzzerWrap" class="player-buzzer-wrap hidden">
            <button id="buzzerBtn" class="buzzer-btn">ЖМИ</button>
          </div>
        </div>
      </section>

      <section id="hostGameScreen" class="screen card">
        <div class="card-head">
          <h2>Игра</h2>
          <div class="row">
            <div class="pill">Код: <span id="hostGameCode"></span></div>
            <button class="btn secondary" id="backToLobbyBtn">К лобби</button>
          </div>
        </div>
        <div class="card-body">
          <div id="hostPlayersBoard" class="players-grid"></div>
          <div id="hostGameBoard" class="board mt16"></div>
        </div>
      </section>

      <section id="adminScreen" class="screen card">
        <div class="card-head">
          <h2>Админка</h2>
          <div class="row">
            <button class="btn secondary" id="refreshAdminBtn">Обновить</button>
            <button class="btn secondary back-home">Выйти из админки</button>
          </div>
        </div>
        <div class="card-body">
          <div class="card inner-card">
            <div class="inner-head"><h3>Забаненные пользователи</h3></div>
            <div id="bansList" class="topics-list"></div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <div id="hostQuestionOverlay" class="overlay hidden">
    <div class="modal">
      <div class="modal-head">
        <div>
          <div id="hqTheme" class="muted"></div>
          <div id="hqPoints" class="modal-points"></div>
        </div>
        <div class="timer-box">
          <div class="muted">Таймер</div>
          <div id="timerValue" class="timer-value">30</div>
        </div>
      </div>
      <div id="hqQuestion" class="modal-question"></div>
      <div id="hqAnswerBox" class="answer-box hidden">
        <div class="muted">Ответ</div>
        <div id="hqAnswer" class="answer-text"></div>
      </div>
      <div class="row mt16">
        <button class="btn gold" id="timerStartBtn">Старт таймера</button>
        <button class="btn secondary" id="timerStopBtn">Стоп</button>
        <button class="btn secondary" id="showAnswerBtn">Показать ответ</button>
        <button class="btn success" id="correctBtn">+ очки</button>
        <button class="btn danger" id="wrongBtn">- очки</button>
        <button class="btn secondary" id="closeQuestionBtn">Закрыть вопрос</button>
      </div>
      <div class="mt16">
        <div class="muted">Сейчас отвечает:</div>
        <div id="currentBuzzed" class="buzzed-box">Пока никто не нажал</div>
      </div>
    </div>
  </div>

  <div id="loginOverlay" class="overlay hidden">
    <div class="modal small-modal">
      <div class="modal-head"><h3>Вход / регистрация</h3></div>
      <div class="tabs row">
        <button class="btn secondary" id="showLoginTab">Вход</button>
        <button class="btn secondary" id="showRegisterTab">Регистрация</button>
      </div>
      <form id="loginForm" class="mt16">
        <div class="field"><label for="loginName">Логин</label><input id="loginName" required /></div>
        <div class="field"><label for="loginPass">Пароль</label><input id="loginPass" type="password" required /></div>
        <div class="row mt16">
          <button class="btn gold" type="submit">Войти</button>
          <button class="btn secondary" type="button" id="closeLoginOverlayBtn">Закрыть</button>
        </div>
        <div id="loginStatus" class="status"></div>
      </form>
      <form id="registerForm" class="mt16 hidden">
        <div class="field"><label for="regName">Логин</label><input id="regName" required /></div>
        <div class="field"><label for="regPass">Пароль</label><input id="regPass" type="password" required /></div>
        <div class="row mt16">
          <button class="btn gold" type="submit">Зарегистрироваться</button>
          <button class="btn secondary" type="button" id="closeLoginOverlayBtn2">Закрыть</button>
        </div>
        <div id="registerStatus" class="status"></div>
      </form>
    </div>
  </div>

  <div id="banOverlay" class="overlay hidden">
    <div class="modal small-modal">
      <div class="modal-head"><h3>Бан пользователя</h3></div>
      <div id="banUserInfo" class="muted"></div>
      <div class="field mt16">
        <label for="banDays">Сколько дней</label>
        <input id="banDays" type="number" min="1" placeholder="Например, 7" />
      </div>
      <div class="row mt16">
        <button class="btn gold" id="applyBanBtn">Забанить на дни</button>
        <button class="btn danger" id="applyForeverBanBtn">Навсегда</button>
        <button class="btn secondary" id="closeBanOverlayBtn">Закрыть</button>
      </div>
      <div id="banStatus" class="status"></div>
    </div>
  </div>

  <script>
    window.APP_BOOT = { user: <?php echo json_encode($user, JSON_UNESCAPED_UNICODE); ?> };
  </script>
  <script src="app.js"></script>
</body>
</html>
