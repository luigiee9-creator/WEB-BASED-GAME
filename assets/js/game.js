// ============================================================
//  Philippine History Memory Card Game — Game Engine
// ============================================================
'use strict';

class MemoryGame {
  constructor(config) {
    // config: { cards, sessionId, difficulty, pairsCount }
    this.cards        = config.cards;        // [{id, title, icon, description, period_id}, ...]
    this.sessionId    = config.sessionId;
    this.difficulty   = config.difficulty;
    this.pairsCount   = config.pairsCount;

    this.flipped      = [];        // [{instanceId, cardId, el}, ...]
    this.matched      = new Set(); // matched card_ids
    this.score        = 0;
    this.quizCorrect  = 0;
    this.totalQuiz    = 0;
    this.attempts     = 0;
    this.isLocked     = false;
    this.startTime    = Date.now();
    this.elapsed      = 0;
    this.timerHandle  = null;
    this.timeLimits   = { easy: 120, medium: 240, hard: 360 };

    // DOM refs
    this.$grid        = document.getElementById('card-grid');
    this.$score       = document.getElementById('hud-score');
    this.$matches     = document.getElementById('hud-matches');
    this.$timer       = document.getElementById('hud-timer');
    this.$progress    = document.getElementById('hud-progress-bar');
    this.$progressLbl = document.getElementById('hud-progress-label');
    this.$modal       = document.getElementById('quiz-modal');
  }

  // ── Init ────────────────────────────────────────────────────
  init() {
    this.renderGrid();
    this.startTimer();
  }

  // ── Grid Rendering ──────────────────────────────────────────
  renderGrid() {
    // Duplicate each card to form pairs
    const doubled = this.cards.flatMap((c, i) => [
      { ...c, instanceId: `ci_${i}_a` },
      { ...c, instanceId: `ci_${i}_b` },
    ]);

    // Fisher-Yates shuffle
    for (let i = doubled.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [doubled[i], doubled[j]] = [doubled[j], doubled[i]];
    }

    const gridClass = `grid-${doubled.length}`;
    this.$grid.className = `card-grid ${gridClass}`;

    this.$grid.innerHTML = doubled.map(c => this.cardHTML(c)).join('');

    // Bind click events
    this.$grid.querySelectorAll('.memory-card').forEach(el => {
      el.addEventListener('click', () => {
        const iid = el.dataset.instanceId;
        const cid = parseInt(el.dataset.cardId, 10);
        this.flipCard(iid, cid, el);
      });
    });
  }

  cardHTML(c) {
    const periodColors = ['#cd853f','#dc2626','#3b82f6','#6b8e23','#c8992a'];
    const dotColor = periodColors[(c.period_id || 1) - 1] || '#c8992a';
    return `
      <div class="memory-card"
           data-instance-id="${c.instanceId}"
           data-card-id="${c.id}"
           id="mc-${c.instanceId}">
        <div class="card-inner">
          <div class="card-face card-back">
            <div class="back-pattern"></div>
            <span class="back-icon">📜</span>
          </div>
          <div class="card-face card-front">
            <span class="card-emoji">${this.esc(c.icon)}</span>
            <span class="card-title">${this.esc(c.title)}</span>
            <div class="card-period-dot" style="background:${dotColor}"></div>
          </div>
        </div>
      </div>`;
  }

  // ── Flip Logic ───────────────────────────────────────────────
  flipCard(instanceId, cardId, el) {
    if (this.isLocked) return;
    if (el.classList.contains('flipped')) return;
    if (el.classList.contains('matched')) return;
    if (this.flipped.length >= 2) return;

    el.classList.add('flipped');
    this.flipped.push({ instanceId, cardId, el });

    if (this.flipped.length === 2) {
      this.attempts++;
      this.isLocked = true;
      setTimeout(() => this.checkMatch(), 600);
    }
  }

  checkMatch() {
    const [a, b] = this.flipped;
    if (a.cardId === b.cardId) {
      // ✅ Match!
      a.el.classList.add('matched');
      b.el.classList.add('matched');
      this.matched.add(a.cardId);
      this.score += 10;
      this.updateHUD();
      this.flipped = [];
      // Show quiz after short delay
      setTimeout(() => this.fetchQuestion(a.cardId), 400);
    } else {
      // ❌ No match
      setTimeout(() => {
        a.el.classList.remove('flipped');
        b.el.classList.remove('flipped');
        a.el.classList.add('shake');
        b.el.classList.add('shake');
        setTimeout(() => {
          a.el.classList.remove('shake');
          b.el.classList.remove('shake');
        }, 500);
        this.flipped = [];
        this.isLocked = false;
      }, 900);
    }
  }

  // ── Quiz Flow ────────────────────────────────────────────────
  async fetchQuestion(cardId) {
    try {
      const res = await fetch('api/game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_question', card_id: cardId, session_id: this.sessionId }),
      });
      const data = await res.json();
      if (data.success && data.question) {
        this.showQuizModal(data.question, data.answers, cardId);
      } else {
        // No question? Just continue
        this.afterQuiz();
      }
    } catch {
      this.afterQuiz();
    }
  }

  showQuizModal(question, answers, cardId) {
    const card = this.cards.find(c => c.id === cardId) || {};
    const letters = ['A', 'B', 'C', 'D'];

    document.getElementById('qz-match-card').textContent = card.title || '';
    document.getElementById('qz-question').textContent   = question.question_text;

    const container = document.getElementById('qz-answers');
    container.innerHTML = answers.map((ans, i) => `
      <button class="answer-btn" data-answer-id="${ans.id}"
              onclick="game.submitAnswer(${question.id}, ${ans.id}, this)">
        <span class="ans-letter">${letters[i] || i + 1}</span>
        <span>${this.esc(ans.answer_text)}</span>
      </button>`).join('');

    document.getElementById('qz-feedback').className = 'quiz-feedback';
    document.getElementById('qz-feedback').innerHTML  = '';
    document.getElementById('btn-continue').className = 'btn-continue';
    document.getElementById('btn-continue').onclick   = () => this.closeModal();

    this.$modal.style.display = 'flex';
    this.$modal.style.animation = 'fadeIn 0.2s ease';
  }

  async submitAnswer(questionId, answerId, btn) {
    // Disable all answer buttons immediately
    document.querySelectorAll('.answer-btn').forEach(b => b.disabled = true);

    try {
      const res = await fetch('api/game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'submit_answer',
          session_id: this.sessionId,
          question_id: questionId,
          answer_id:   answerId,
        }),
      });
      const data = await res.json();
      this.totalQuiz++;

      const fb     = document.getElementById('qz-feedback');
      const contBtn = document.getElementById('btn-continue');

      if (data.is_correct) {
        this.quizCorrect++;
        this.score += 20;
        btn.classList.add('correct');
        fb.className = 'quiz-feedback correct-fb show';
        fb.innerHTML = `<div class="fb-title">✅ Correct!</div>
                        <div class="fb-detail">${this.esc(data.explanation || '')}</div>`;
      } else {
        btn.classList.add('wrong');
        // Highlight correct answer
        document.querySelectorAll('.answer-btn').forEach(b => {
          if (parseInt(b.dataset.answerId, 10) === data.correct_answer_id) {
            b.classList.add('correct');
          }
        });
        fb.className = 'quiz-feedback wrong-fb show';
        fb.innerHTML = `<div class="fb-title">❌ Incorrect</div>
                        <div class="fb-detail">Correct answer: <strong>${this.esc(data.correct_answer_text)}</strong></div>`;
      }

      contBtn.className = 'btn-continue show';
      this.updateHUD();
    } catch {
      document.getElementById('btn-continue').className = 'btn-continue show';
    }
  }

  closeModal() {
    this.$modal.style.display = 'none';
    this.isLocked = false;

    if (this.matched.size === this.pairsCount) {
      setTimeout(() => this.endGame(), 300);
    }
  }

  afterQuiz() {
    this.isLocked = false;
    if (this.matched.size === this.pairsCount) {
      setTimeout(() => this.endGame(), 300);
    }
  }

  // ── HUD Updates ──────────────────────────────────────────────
  updateHUD() {
    this.$score.textContent   = this.score;
    this.$matches.textContent = `${this.matched.size}/${this.pairsCount}`;
    const pct = Math.round((this.matched.size / this.pairsCount) * 100);
    this.$progress.style.width       = pct + '%';
    this.$progressLbl.textContent    = `${this.matched.size} of ${this.pairsCount} pairs found`;
  }

  // ── Timer ────────────────────────────────────────────────────
  startTimer() {
    this.timerHandle = setInterval(() => {
      this.elapsed = Math.floor((Date.now() - this.startTime) / 1000);
      const m = String(Math.floor(this.elapsed / 60)).padStart(2, '0');
      const s = String(this.elapsed % 60).padStart(2, '0');
      this.$timer.textContent = `${m}:${s}`;
      const limit = this.timeLimits[this.difficulty] || 240;
      if (this.elapsed > limit * 0.75) {
        this.$timer.classList.add('warning');
      }
    }, 1000);
  }

  stopTimer() {
    clearInterval(this.timerHandle);
  }

  // ── Game End ─────────────────────────────────────────────────
  async endGame() {
    this.stopTimer();
    this.elapsed = Math.floor((Date.now() - this.startTime) / 1000);

    try {
      await fetch('api/game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:          'end_session',
          session_id:      this.sessionId,
          total_time:      this.elapsed,
          score:           this.score,
          correct_answers: this.quizCorrect,
          total_questions: this.totalQuiz,
          matched_pairs:   this.matched.size,
        }),
      });
    } catch { /* best-effort */ }

    this.showEndScreen();
  }

  showEndScreen() {
    const limit    = this.timeLimits[this.difficulty] || 240;
    const timeBonus = Math.round(Math.max(0, (limit - this.elapsed) / limit) * 100);
    const finalScore = this.score + timeBonus;
    const accuracy = this.totalQuiz > 0
      ? Math.round((this.quizCorrect / this.totalQuiz) * 100) : 0;

    const m = String(Math.floor(this.elapsed / 60)).padStart(2, '0');
    const s = String(this.elapsed % 60).padStart(2, '0');

    const modal = document.getElementById('end-modal');
    document.getElementById('end-score').textContent     = finalScore;
    document.getElementById('end-matches').textContent   = this.matched.size;
    document.getElementById('end-correct').textContent   = `${this.quizCorrect}/${this.totalQuiz}`;
    document.getElementById('end-accuracy').textContent  = accuracy + '%';
    document.getElementById('end-time').textContent      = `${m}:${s}`;
    document.getElementById('end-timebonus').textContent = '+' + timeBonus;
    modal.style.display = 'flex';
  }

  // ── Utility ─────────────────────────────────────────────────
  esc(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }
}

// Global game instance (set in game.php inline script)
let game = null;
