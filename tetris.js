'use strict';

const COLS = 10;
const ROWS = 20;
const BLOCK = 30;

const COLORS = {
  I: '#00e5ff', O: '#ffd400', T: '#b04dff',
  S: '#2ee66b', Z: '#ff3d55', J: '#3d6bff', L: '#ff9a1f',
};

const SHAPES = {
  I: [[0, 0, 0, 0], [1, 1, 1, 1], [0, 0, 0, 0], [0, 0, 0, 0]],
  O: [[1, 1], [1, 1]],
  T: [[0, 1, 0], [1, 1, 1], [0, 0, 0]],
  S: [[0, 1, 1], [1, 1, 0], [0, 0, 0]],
  Z: [[1, 1, 0], [0, 1, 1], [0, 0, 0]],
  J: [[1, 0, 0], [1, 1, 1], [0, 0, 0]],
  L: [[0, 0, 1], [1, 1, 1], [0, 0, 0]],
};

const LINE_POINTS = [0, 100, 300, 500, 800];

const boardCanvas = document.getElementById('board');
const ctx = boardCanvas.getContext('2d');
const nextCtx = document.getElementById('next').getContext('2d');
const holdCtx = document.getElementById('hold').getContext('2d');
const overlay = document.getElementById('overlay');
const scoreEl = document.getElementById('score');
const linesEl = document.getElementById('lines');
const levelEl = document.getElementById('level');

let board, current, nextType, holdType, canHold, bag;
let score, lines, level;
let dropCounter, lastTime, running, paused, gameOver;

function createBoard() {
  return Array.from({ length: ROWS }, () => Array(COLS).fill(null));
}

// Tirage « 7-bag » : chaque pièce apparaît une fois par série de 7.
function drawFromBag() {
  if (!bag || bag.length === 0) {
    bag = Object.keys(SHAPES);
    for (let i = bag.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [bag[i], bag[j]] = [bag[j], bag[i]];
    }
  }
  return bag.pop();
}

function makePiece(type) {
  const matrix = SHAPES[type].map(row => row.slice());
  return {
    type,
    matrix,
    x: Math.floor((COLS - matrix[0].length) / 2),
    y: type === 'I' ? -1 : 0,
  };
}

function collides(piece, dx = 0, dy = 0, matrix = piece.matrix) {
  for (let y = 0; y < matrix.length; y++) {
    for (let x = 0; x < matrix[y].length; x++) {
      if (!matrix[y][x]) continue;
      const nx = piece.x + x + dx;
      const ny = piece.y + y + dy;
      if (nx < 0 || nx >= COLS || ny >= ROWS) return true;
      if (ny >= 0 && board[ny][nx]) return true;
    }
  }
  return false;
}

function rotateMatrix(matrix, dir) {
  const n = matrix.length;
  const out = Array.from({ length: n }, () => Array(n).fill(0));
  for (let y = 0; y < n; y++) {
    for (let x = 0; x < n; x++) {
      if (dir > 0) out[x][n - 1 - y] = matrix[y][x];
      else out[n - 1 - x][y] = matrix[y][x];
    }
  }
  return out;
}

function rotate(dir) {
  if (current.type === 'O') return;
  const rotated = rotateMatrix(current.matrix, dir);
  // Petits décalages (« wall kicks ») pour permettre la rotation contre un mur.
  for (const [kx, ky] of [[0, 0], [-1, 0], [1, 0], [-2, 0], [2, 0], [0, -1]]) {
    if (!collides(current, kx, ky, rotated)) {
      current.matrix = rotated;
      current.x += kx;
      current.y += ky;
      return;
    }
  }
}

function move(dx) {
  if (!collides(current, dx, 0)) current.x += dx;
}

function softDrop() {
  if (!collides(current, 0, 1)) {
    current.y++;
    score += 1;
  } else {
    lockPiece();
  }
  dropCounter = 0;
}

function hardDrop() {
  let dist = 0;
  while (!collides(current, 0, 1)) {
    current.y++;
    dist++;
  }
  score += dist * 2;
  lockPiece();
  dropCounter = 0;
}

function hold() {
  if (!canHold) return;
  const type = current.type;
  if (holdType) {
    current = makePiece(holdType);
  } else {
    spawn();
  }
  holdType = type;
  canHold = false;
}

function lockPiece() {
  for (let y = 0; y < current.matrix.length; y++) {
    for (let x = 0; x < current.matrix[y].length; x++) {
      if (!current.matrix[y][x]) continue;
      const by = current.y + y;
      if (by < 0) {
        endGame();
        return;
      }
      board[by][current.x + x] = current.type;
    }
  }
  clearLines();
  spawn();
  canHold = true;
}

function clearLines() {
  let cleared = 0;
  for (let y = ROWS - 1; y >= 0; y--) {
    if (board[y].every(cell => cell)) {
      board.splice(y, 1);
      board.unshift(Array(COLS).fill(null));
      cleared++;
      y++;
    }
  }
  if (cleared) {
    score += LINE_POINTS[cleared] * level;
    lines += cleared;
    level = Math.floor(lines / 10) + 1;
  }
}

function spawn() {
  current = makePiece(nextType);
  nextType = drawFromBag();
  if (collides(current)) endGame();
}

function dropInterval() {
  return Math.max(60, 1000 - (level - 1) * 90);
}

function endGame() {
  gameOver = true;
  running = false;
  showOverlay('Partie terminée');
}

function showOverlay(text) {
  overlay.textContent = text;
  overlay.hidden = false;
}

function drawCell(c, x, y, size, color, alpha = 1) {
  c.globalAlpha = alpha;
  c.fillStyle = color;
  c.fillRect(x * size, y * size, size, size);
  c.fillStyle = 'rgba(255,255,255,0.25)';
  c.fillRect(x * size, y * size, size, 3);
  c.strokeStyle = 'rgba(0,0,0,0.5)';
  c.strokeRect(x * size + 0.5, y * size + 0.5, size - 1, size - 1);
  c.globalAlpha = 1;
}

function drawMatrix(c, matrix, ox, oy, size, color, alpha) {
  matrix.forEach((row, y) => row.forEach((v, x) => {
    if (v && oy + y >= 0) drawCell(c, ox + x, oy + y, size, color, alpha);
  }));
}

function drawPreview(c, type) {
  c.clearRect(0, 0, c.canvas.width, c.canvas.height);
  if (!type) return;
  const m = SHAPES[type];
  const size = 24;
  // Centrer sur les cases réellement occupées.
  const rows = m.map((r, i) => (r.some(Boolean) ? i : -1)).filter(i => i >= 0);
  const cols = m[0].map((_, i) => (m.some(r => r[i]) ? i : -1)).filter(i => i >= 0);
  const w = cols.length * size;
  const h = rows.length * size;
  c.save();
  c.translate((c.canvas.width - w) / 2 - cols[0] * size, (c.canvas.height - h) / 2 - rows[0] * size);
  drawMatrix(c, m, 0, 0, size, COLORS[type]);
  c.restore();
}

function draw() {
  ctx.clearRect(0, 0, boardCanvas.width, boardCanvas.height);

  ctx.strokeStyle = '#141a2b';
  for (let x = 1; x < COLS; x++) {
    ctx.beginPath(); ctx.moveTo(x * BLOCK, 0); ctx.lineTo(x * BLOCK, ROWS * BLOCK); ctx.stroke();
  }
  for (let y = 1; y < ROWS; y++) {
    ctx.beginPath(); ctx.moveTo(0, y * BLOCK); ctx.lineTo(COLS * BLOCK, y * BLOCK); ctx.stroke();
  }

  board.forEach((row, y) => row.forEach((type, x) => {
    if (type) drawCell(ctx, x, y, BLOCK, COLORS[type]);
  }));

  if (current && !gameOver) {
    let ghostY = 0;
    while (!collides(current, 0, ghostY + 1)) ghostY++;
    drawMatrix(ctx, current.matrix, current.x, current.y + ghostY, BLOCK, COLORS[current.type], 0.2);
    drawMatrix(ctx, current.matrix, current.x, current.y, BLOCK, COLORS[current.type]);
  }

  drawPreview(nextCtx, nextType);
  drawPreview(holdCtx, holdType);

  scoreEl.textContent = score;
  linesEl.textContent = lines;
  levelEl.textContent = level;
}

function update(time = 0) {
  if (!running) return;
  const delta = time - lastTime;
  lastTime = time;
  if (!paused) {
    dropCounter += delta;
    if (dropCounter > dropInterval()) {
      if (!collides(current, 0, 1)) current.y++;
      else lockPiece();
      dropCounter = 0;
    }
  }
  draw();
  if (running) requestAnimationFrame(update);
}

function start() {
  board = createBoard();
  bag = [];
  score = 0;
  lines = 0;
  level = 1;
  holdType = null;
  canHold = true;
  gameOver = false;
  paused = false;
  nextType = drawFromBag();
  spawn();
  dropCounter = 0;
  lastTime = performance.now();
  overlay.hidden = true;
  if (!running) {
    running = true;
    requestAnimationFrame(update);
  }
}

function togglePause() {
  if (!running) return;
  paused = !paused;
  if (paused) showOverlay('Pause');
  else overlay.hidden = true;
}

document.addEventListener('keydown', e => {
  if (e.code === 'Enter' && !running) {
    start();
    return;
  }
  if (!running) return;
  if (e.code === 'KeyP' || e.code === 'Escape') {
    togglePause();
    return;
  }
  if (paused) return;

  switch (e.code) {
    case 'ArrowLeft': move(-1); break;
    case 'ArrowRight': move(1); break;
    case 'ArrowDown': softDrop(); break;
    case 'ArrowUp':
    case 'KeyX': rotate(1); break;
    case 'KeyZ':
    case 'KeyW': rotate(-1); break;
    case 'Space': hardDrop(); break;
    case 'KeyC':
    case 'ShiftLeft':
    case 'ShiftRight': hold(); break;
    default: return;
  }
  e.preventDefault();
  draw();
});

document.getElementById('start').addEventListener('click', e => {
  e.currentTarget.blur();
  start();
});

board = createBoard();
score = 0; lines = 0; level = 1;
draw();
showOverlay('Appuie sur Jouer');
