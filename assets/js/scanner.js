(() => {
  const root = document.querySelector('[data-scanner]');
  if (!root) return;

  const examId = Number(root.dataset.examId);
  const questionCount = Number(root.dataset.questionCount);
  const input = document.getElementById('scanInput');
  const canvas = document.getElementById('scanCanvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const previewWrap = document.querySelector('[data-preview-wrap]');
  const scanActions = document.querySelector('[data-scan-actions]');
  const processBtn = document.querySelector('[data-process-scan]');
  const processLabel = document.querySelector('[data-process-label]');
  const changePhoto = document.querySelector('[data-change-photo]');
  const review = document.querySelector('[data-review]');
  const reviewAlert = document.querySelector('[data-review-alert]');
  const detectedGrid = document.querySelector('[data-detected-grid]');
  const confidenceEl = document.querySelector('[data-confidence]');
  const manualStudent = document.getElementById('manualStudent');
  const identityCard = document.querySelector('[data-identity-card]');
  const existingWarning = document.querySelector('[data-existing-warning]');
  const statusEl = document.querySelector('[data-cv-status]');
  const saveBtn = document.querySelector('[data-save-scan]');
  const resultCard = document.querySelector('[data-result-card]');
  const scanNext = document.querySelector('[data-scan-next]');
  const qualityResolution = document.querySelector('[data-quality-resolution]');
  const qualityLight = document.querySelector('[data-quality-light]');
  const qualityMarkers = document.querySelector('[data-quality-markers]');
  const captureChoices = document.querySelector('[data-capture-choices]');
  const openLiveCamera = document.querySelector('[data-open-live-camera]');
  const liveCamera = document.querySelector('[data-live-camera]');
  const cameraVideo = document.querySelector('[data-camera-video]');
  const cameraCapture = document.querySelector('[data-camera-capture]');
  const cameraCancel = document.querySelector('[data-camera-cancel]');
  const cameraTorch = document.querySelector('[data-camera-torch]');
  const cameraFrame = document.querySelector('[data-camera-frame]');
  const cameraGuide = document.querySelector('[data-camera-guide]');
  const guideText = document.querySelector('[data-guide-text]');
  const liveStatus = document.querySelector('[data-live-status]');
  const liveLight = document.querySelector('[data-live-light]');
  const liveMarkers = document.querySelector('[data-live-markers]');
  const liveStability = document.querySelector('[data-live-stability]');
  const liveBadge = document.querySelector('[data-live-badge]');
  const captureLabel = document.querySelector('[data-camera-capture-label]');
  const scannerDiagnostic = document.querySelector('[data-scanner-diagnostic]');
  const geometryOverlay = document.querySelector('[data-geometry-overlay]');
  const alignmentPreview = document.querySelector('[data-alignment-preview]');
  const alignmentCanvas = document.getElementById('alignmentPreviewCanvas');
  const alignmentStatus = document.querySelector('[data-alignment-status]');
  const alignmentBadge = document.querySelector('[data-alignment-badge]');
  const alignmentGeometry = document.querySelector('[data-alignment-geometry]');
  const alignmentGrid = document.querySelector('[data-alignment-grid]');
  const alignmentPerspective = document.querySelector('[data-alignment-perspective]');
  const alignmentSharpness = document.querySelector('[data-alignment-sharpness]');
  const alignmentMessage = document.querySelector('[data-alignment-message]');
  const alignmentConfirm = document.querySelector('[data-alignment-confirm]');
  const alignmentRetakes = document.querySelectorAll('[data-alignment-retake]');

  window.GabaritoScannerReady = true;

  function showScannerDiagnostic(message, type = 'error') {
    if (!scannerDiagnostic) return;
    scannerDiagnostic.hidden = false;
    scannerDiagnostic.className = `scanner-browser-diagnostic ${type}`;
    scannerDiagnostic.textContent = message;
  }

  function clearScannerDiagnostic() {
    if (!scannerDiagnostic) return;
    scannerDiagnostic.hidden = true;
    scannerDiagnostic.textContent = '';
    scannerDiagnostic.className = 'scanner-browser-diagnostic';
  }

  // O evento é ligado logo no início para que o botão continue funcionando
  // mesmo se algum recurso secundário falhar durante a inicialização.
  openLiveCamera?.addEventListener('click', (event) => {
    event.preventDefault();
    clearScannerDiagnostic();
    Promise.resolve(startCamera()).catch((err) => {
      console.error('Falha ao iniciar scanner', err);
      showScannerDiagnostic('Não foi possível iniciar a câmera. Recarregue a página e confira a permissão de câmera do navegador.');
    });
  });

  let cvReady = false;
  let detectedToken = '';
  let selectedStudent = null;
  let detectedAnswers = [];
  let globalConfidence = 0;
  let firstPhoto = true;
  let cameraStream = null;
  let liveDetectTimer = null;
  let liveDetectBusy = false;
  let stableMarkerFrames = 0;
  let torchEnabled = false;
  let scannerHome = null;
  let scannerPlaceholder = null;
  let fullscreenRequested = false;
  let pendingAlignment = null;

  const reportedMemory = Number(navigator.deviceMemory || 4);
  const MAX_IMAGE_SIDE = reportedMemory <= 2 ? 1100 : (reportedMemory <= 4 ? 1350 : 1600);
  const QR_MAX_SIDE = 850;
  const TEMPLATE_W_MM = 178;
  const TEMPLATE_H_MM = 232;
  const NORMALIZED_W = 1000;
  const NORMALIZED_H = Math.round(NORMALIZED_W * TEMPLATE_H_MM / TEMPLATE_W_MM);

  const students = Array.isArray(window.SCAN_STUDENTS) ? window.SCAN_STUDENTS : [];
  const prefillId = Number(window.SCAN_PREFILL || 0);

  function setStatus(text, ready = false) {
    statusEl.textContent = text;
    const pill = statusEl.closest('.scanner-status-pill');
    pill?.classList.toggle('ready', ready);
  }

  async function waitForCv() {
    setStatus('Carregando análise…');
    try {
      if (window.GradeScanVendors?.ensureOpenCV) {
        await window.GradeScanVendors.ensureOpenCV();
      }
      if (window.cv instanceof Promise) window.cv = await window.cv;
      if (!window.cv || typeof window.cv.Mat !== 'function') throw new Error('OpenCV indisponível.');
      cvReady = true;
      setStatus('Leitor pronto', true);
    } catch (err) {
      console.error(err);
      cvReady = false;
      setStatus('Leitor indisponível');
    }
  }
  setStatus('Pronto para fotografar', true);

  function findStudent(id) {
    return students.find(s => Number(s.id) === Number(id)) || null;
  }

  function updateExistingWarning(student) {
    if (!existingWarning) return;
    existingWarning.hidden = !(student && Number(student.corrected) === 1);
    if (!existingWarning.hidden && student.score !== null && student.score !== undefined) {
      existingWarning.textContent = `Este aluno já possui uma correção salva (nota ${Number(student.score).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 })}). Ao salvar novamente, a alteração ficará registrada na auditoria.`;
    }
  }

  function showStudent(student, token = '') {
    selectedStudent = student;
    if (token) detectedToken = token;
    const found = identityCard.querySelector('.identity-found');
    const placeholder = identityCard.querySelector('.identity-placeholder');
    placeholder.hidden = true;
    found.hidden = false;
    found.querySelector('[data-student-initial]').textContent = (student.name || '?').trim().charAt(0).toUpperCase();
    found.querySelector('[data-student-name]').textContent = student.name;
    found.querySelector('[data-student-enrollment]').textContent = student.enrollment;
    document.querySelector('[data-ready-student]').textContent = student.name;
    manualStudent.value = String(student.id);
    updateExistingWarning(student);
  }

  function clearStudent() {
    selectedStudent = null;
    detectedToken = '';
    identityCard.querySelector('.identity-placeholder').hidden = false;
    identityCard.querySelector('.identity-found').hidden = true;
    document.querySelector('[data-ready-student]').textContent = 'Aluno não identificado';
    if (existingWarning) existingWarning.hidden = true;
  }

  manualStudent.addEventListener('change', () => {
    const student = findStudent(manualStudent.value);
    detectedToken = '';
    if (student) showStudent(student);
    else clearStudent();
  });

  if (prefillId) {
    const pre = findStudent(prefillId);
    if (pre) showStudent(pre);
  }

  function makeSmallCanvas(source, maxSide) {
    const sw = source.videoWidth || source.naturalWidth || source.width;
    const sh = source.videoHeight || source.naturalHeight || source.height;
    const scale = Math.min(1, maxSide / Math.max(sw, sh));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(sw * scale));
    c.height = Math.max(1, Math.round(sh * scale));
    c.getContext('2d', { alpha: false }).drawImage(source, 0, 0, c.width, c.height);
    return c;
  }

  function assessLight() {
    if (!canvas.width || !canvas.height) return;
    const sample = document.createElement('canvas');
    const scale = Math.min(1, 280 / Math.max(canvas.width, canvas.height));
    sample.width = Math.max(1, Math.round(canvas.width * scale));
    sample.height = Math.max(1, Math.round(canvas.height * scale));
    const sctx = sample.getContext('2d', { willReadFrequently: true, alpha: false });
    sctx.drawImage(canvas, 0, 0, sample.width, sample.height);
    const image = sctx.getImageData(0, 0, sample.width, sample.height).data;
    let sum = 0;
    let sumSq = 0;
    let count = 0;
    for (let i = 0; i < image.length; i += 4) {
      const lum = image[i] * 0.2126 + image[i + 1] * 0.7152 + image[i + 2] * 0.0722;
      sum += lum;
      sumSq += lum * lum;
      count += 1;
    }
    const avg = count ? sum / count : 0;
    const variance = count ? Math.max(0, sumSq / count - avg * avg) : 0;
    const contrast = Math.sqrt(variance);
    let label = 'Luz: boa';
    if (avg < 65) label = 'Luz: foto escura';
    else if (avg > 235) label = 'Luz: foto muito clara';
    else if (contrast < 30) label = 'Luz: pouco contraste';
    qualityLight.textContent = label;
  }

  function resetForNewPhoto() {
    detectedToken = '';
    if (!firstPhoto || !prefillId) {
      manualStudent.value = '';
      clearStudent();
    }
    firstPhoto = false;
    detectedAnswers = [];
    globalConfidence = 0;
    qualityMarkers.textContent = 'Marcadores: aguardando';
  }

  function drawToMainCanvas(source, originalW, originalH) {
    const sw = source.videoWidth || source.naturalWidth || source.width;
    const sh = source.videoHeight || source.naturalHeight || source.height;
    const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(sw, sh));
    const w = Math.max(1, Math.round(sw * scale));
    const h = Math.max(1, Math.round(sh * scale));
    canvas.width = w;
    canvas.height = h;
    ctx.clearRect(0, 0, w, h);
    ctx.drawImage(source, 0, 0, w, h);
    const minSide = Math.min(originalW || sw, originalH || sh);
    qualityResolution.textContent = minSide >= 1200
      ? `Resolução: boa (${originalW || sw}×${originalH || sh}) • processando em ${w}×${h}`
      : `Resolução: baixa (${originalW || sw}×${originalH || sh})`;
    assessLight();
    previewWrap.hidden = false;
    scanActions.hidden = true;
    review.hidden = true;
    resultCard.hidden = true;
    captureChoices.hidden = true;
    liveCamera.hidden = true;
    processBtn.disabled = false;
    processLabel.textContent = 'Processando…';
    setStatus(`Modo leve ativo • ${w}×${h}`, true);
    tryIdentifyQr();
  }

  async function readImageDimensions(file) {
    try {
      const buffer = await file.slice(0, Math.min(file.size, 512 * 1024)).arrayBuffer();
      const view = new DataView(buffer);
      if (view.byteLength >= 24 && view.getUint32(0) === 0x89504E47 && view.getUint32(4) === 0x0D0A1A0A) {
        return { width: view.getUint32(16), height: view.getUint32(20) };
      }
      if (view.byteLength < 4 || view.getUint16(0) !== 0xFFD8) return null;
      let offset = 2;
      const sof = new Set([0xFFC0,0xFFC1,0xFFC2,0xFFC3,0xFFC5,0xFFC6,0xFFC7,0xFFC9,0xFFCA,0xFFCB,0xFFCD,0xFFCE,0xFFCF]);
      while (offset + 9 < view.byteLength) {
        if (view.getUint8(offset) !== 0xFF) { offset += 1; continue; }
        const marker = 0xFF00 | view.getUint8(offset + 1);
        if (sof.has(marker)) {
          return { width: view.getUint16(offset + 7), height: view.getUint16(offset + 5) };
        }
        if (marker === 0xFFD9 || marker === 0xFFDA) break;
        const len = view.getUint16(offset + 2);
        if (!len || len < 2) break;
        offset += 2 + len;
      }
    } catch (err) {
      console.warn('Não foi possível ler as dimensões antes da decodificação.', err);
    }
    return null;
  }

  async function decodeFileSafely(file) {
    const dims = await readImageDimensions(file);
    if (typeof createImageBitmap === 'function') {
      try {
        let opts = { resizeQuality: 'high' };
        if (dims) {
          const scale = Math.min(1, MAX_IMAGE_SIDE / Math.max(dims.width, dims.height));
          opts.resizeWidth = Math.max(1, Math.round(dims.width * scale));
          opts.resizeHeight = Math.max(1, Math.round(dims.height * scale));
        }
        const bitmap = await createImageBitmap(file, opts);
        return { source: bitmap, originalW: dims?.width || bitmap.width, originalH: dims?.height || bitmap.height, release: () => bitmap.close?.() };
      } catch (err) {
        console.warn('createImageBitmap falhou; usando carregamento compatível.', err);
      }
    }
    return await new Promise((resolve, reject) => {
      const img = new Image();
      const objectUrl = URL.createObjectURL(file);
      img.onload = () => resolve({
        source: img,
        originalW: img.naturalWidth,
        originalH: img.naturalHeight,
        release: () => { img.src = ''; URL.revokeObjectURL(objectUrl); }
      });
      img.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('Não foi possível abrir esta imagem. Tente novamente em JPG ou PNG.')); };
      img.src = objectUrl;
    });
  }

  async function loadFile(file) {
    if (!file) return;
    if (!String(file.type || '').startsWith('image/')) {
      alert('Selecione uma foto do gabarito.');
      input.value = '';
      return;
    }
    if (file.size > 25 * 1024 * 1024) {
      alert('A foto é muito grande. Use o scanner do navegador para capturar em resolução segura.');
      input.value = '';
      return;
    }
    resetForNewPhoto();
    setStatus('Preparando foto…');
    try {
      const decoded = await decodeFileSafely(file);
      try {
        drawToMainCanvas(decoded.source, decoded.originalW, decoded.originalH);
      } finally {
        decoded.release?.();
      }
    } catch (err) {
      console.error(err);
      const message = /memory|mem[oó]ria|allocation|array buffer|out of/i.test(String(err?.message || err))
        ? 'O celular ficou sem memória ao abrir esta foto. Use o scanner do navegador, que captura em resolução segura.'
        : (err?.message || 'Não foi possível abrir esta imagem. Tente fotografar novamente.');
      alert(message);
      setStatus('Pronto para fotografar', true);
    }
  }

  input?.addEventListener('change', () => loadFile(input.files?.[0]));

  function setLiveMessage(text) {
    if (liveStatus) liveStatus.textContent = text;
  }

  function clearGeometryOverlay() {
    if (!geometryOverlay) return;
    const gctx = geometryOverlay.getContext('2d');
    gctx?.clearRect(0, 0, geometryOverlay.width, geometryOverlay.height);
  }

  function frameGeometryMetrics(frame, w, h) {
    if (!frame) return { score: 0, perspective: 0, coverage: 0 };
    const top = pointDistance(frame.tl, frame.tr);
    const bottom = pointDistance(frame.bl, frame.br);
    const left = pointDistance(frame.tl, frame.bl);
    const right = pointDistance(frame.tr, frame.br);
    const diag1 = pointDistance(frame.tl, frame.br);
    const diag2 = pointDistance(frame.tr, frame.bl);
    const widthBalance = Math.min(top, bottom) / Math.max(1, Math.max(top, bottom));
    const heightBalance = Math.min(left, right) / Math.max(1, Math.max(left, right));
    const diagBalance = Math.min(diag1, diag2) / Math.max(1, Math.max(diag1, diag2));
    const perspective = Math.max(0, Math.min(1, (widthBalance + heightBalance + diagBalance) / 3));
    const coverage = polygonArea([frame.tl, frame.tr, frame.br, frame.bl]) / Math.max(1, w * h);
    const coverageScore = Math.max(0, Math.min(1, coverage / 0.18));
    let score = Math.round((perspective * 0.72 + coverageScore * 0.28) * 100);
    if (frame.loose) score -= 8;
    return { score: Math.max(0, Math.min(100, score)), perspective, coverage };
  }

  function drawGeometryOverlay(sample, frame, metrics, lightOk) {
    if (!geometryOverlay || !sample || !frame) {
      clearGeometryOverlay();
      return;
    }
    geometryOverlay.width = sample.width;
    geometryOverlay.height = sample.height;
    const gctx = geometryOverlay.getContext('2d');
    gctx.clearRect(0, 0, sample.width, sample.height);
    const good = lightOk && metrics.score >= 66 && !frame.loose;
    const stroke = good ? '#30d783' : '#f5c451';
    const fill = good ? 'rgba(48,215,131,.10)' : 'rgba(245,196,81,.09)';
    const pts = [frame.tl, frame.tr, frame.br, frame.bl];
    gctx.beginPath();
    gctx.moveTo(pts[0].x, pts[0].y);
    pts.slice(1).forEach(p => gctx.lineTo(p.x, p.y));
    gctx.closePath();
    gctx.fillStyle = fill;
    gctx.fill();
    gctx.strokeStyle = stroke;
    gctx.lineWidth = Math.max(2, sample.width / 180);
    gctx.stroke();
    const markers = [frame.tl, frame.tr, frame.br, frame.bl, frame.ml, frame.mr];
    markers.forEach((m, i) => {
      gctx.beginPath();
      gctx.arc(m.x, m.y, Math.max(4, sample.width / 95), 0, Math.PI * 2);
      gctx.fillStyle = frame.loose && i >= 4 ? '#f5c451' : stroke;
      gctx.fill();
      gctx.lineWidth = 2;
      gctx.strokeStyle = '#fff';
      gctx.stroke();
    });
  }

  function resetLiveUi() {
    stableMarkerFrames = 0;
    if (cameraCapture) cameraCapture.disabled = true;
    if (captureLabel) captureLabel.textContent = 'Aponte para o gabarito';
    if (liveMarkers) liveMarkers.innerHTML = '<i></i>Marcadores: 0/6';
    if (liveStability) liveStability.innerHTML = '<i></i>Enquadramento: aguardando';
    if (liveLight) liveLight.innerHTML = '<i></i>Luz: verificando';
    if (guideText) guideText.textContent = 'Enquadre o bloco do gabarito';
    cameraGuide?.classList.remove('scanner-guide-ready', 'scanner-guide-warning');
    liveBadge?.classList.remove('ready', 'warning');
    if (liveBadge) liveBadge.innerHTML = '<i></i><span>Procurando 6 marcadores</span>';
    setLiveMessage('Procurando folha…');
    clearGeometryOverlay();
  }

  function stopLiveDetection() {
    if (liveDetectTimer) {
      clearInterval(liveDetectTimer);
      liveDetectTimer = null;
    }
    liveDetectBusy = false;
    stableMarkerFrames = 0;
  }

  function assessCanvasLight(sampleCanvas) {
    const cctx = sampleCanvas.getContext('2d', { willReadFrequently: true, alpha: false });
    const data = cctx.getImageData(0, 0, sampleCanvas.width, sampleCanvas.height).data;
    let sum = 0;
    let sumSq = 0;
    let count = 0;
    const stride = Math.max(1, Math.floor((sampleCanvas.width * sampleCanvas.height) / 26000));
    for (let px = 0; px < sampleCanvas.width * sampleCanvas.height; px += stride) {
      const i = px * 4;
      const lum = data[i] * 0.2126 + data[i + 1] * 0.7152 + data[i + 2] * 0.0722;
      sum += lum;
      sumSq += lum * lum;
      count++;
    }
    const avg = count ? sum / count : 0;
    const variance = count ? Math.max(0, sumSq / count - avg * avg) : 0;
    const contrast = Math.sqrt(variance);
    if (avg < 55) return { ok: false, label: 'Luz: muito escura' };
    if (avg > 242) return { ok: false, label: 'Luz: muito clara' };
    if (contrast < 25) return { ok: false, label: 'Luz: pouco contraste' };
    return { ok: true, label: 'Luz: boa' };
  }

  function smallVideoCanvas(maxSide = 480) {
    const sw = cameraVideo.videoWidth;
    const sh = cameraVideo.videoHeight;
    if (!sw || !sh) return null;
    const scale = Math.min(1, maxSide / Math.max(sw, sh));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(sw * scale));
    c.height = Math.max(1, Math.round(sh * scale));
    c.getContext('2d', { alpha: false }).drawImage(cameraVideo, 0, 0, c.width, c.height);
    return c;
  }

  function updateLiveReady(ready, lightOk, message = '', frame = null, metrics = null) {
    const geometryOk = !metrics || metrics.score >= 58;
    if (ready && lightOk && geometryOk) {
      stableMarkerFrames = Math.min(5, stableMarkerFrames + 1);
    } else {
      stableMarkerFrames = 0;
    }
    const stable = stableMarkerFrames >= 3;
    if (cameraCapture) cameraCapture.disabled = !stable;
    cameraGuide?.classList.toggle('scanner-guide-ready', stable);
    cameraGuide?.classList.toggle('scanner-guide-warning', !stable && ready);
    liveBadge?.classList.toggle('ready', stable);
    liveBadge?.classList.toggle('warning', !stable && ready);

    if (ready) {
      const markerText = frame?.loose ? 'Marcadores: 5/6 + estimado' : 'Marcadores: 6/6';
      if (liveMarkers) liveMarkers.innerHTML = `<i></i>${markerText}`;
      if (liveStability) liveStability.innerHTML = metrics ? `<i></i>Alinhamento: ${metrics.score}%` : (stable ? '<i></i>Enquadramento: pronto' : '<i></i>Enquadramento: segure firme');
      if (guideText) guideText.textContent = stable ? 'Alinhamento pronto — segure firme' : (message || 'Marcadores encontrados');
      if (liveBadge) liveBadge.innerHTML = stable ? '<i></i><span>Bloco pronto para capturar</span>' : `<i></i><span>${metrics && metrics.score < 66 ? 'Ajuste um pouco o ângulo' : 'Segure o celular firme'}</span>`;
      if (captureLabel) captureLabel.textContent = stable ? 'Capturar e pré-visualizar' : 'Ajustando alinhamento…';
      setLiveMessage(stable ? 'Alinhamento pronto' : 'Validando geometria…');
    } else {
      if (liveMarkers) liveMarkers.innerHTML = '<i></i>Marcadores: procurando';
      if (liveStability) liveStability.innerHTML = '<i></i>Alinhamento: aguardando';
      if (guideText) guideText.textContent = message || 'Mostre os 6 quadrados do bloco';
      if (liveBadge) liveBadge.innerHTML = '<i></i><span>Procurando marcadores</span>';
      if (captureLabel) captureLabel.textContent = 'Aponte para o gabarito';
      setLiveMessage(message || 'Procurando bloco…');
    }
  }

  function checkLiveFrame() {
    if (liveDetectBusy || !cameraStream || !cameraVideo.videoWidth) return;
    liveDetectBusy = true;
    let src = null;
    try {
      const sample = smallVideoCanvas(480);
      if (!sample) return;
      const light = assessCanvasLight(sample);
      if (liveLight) liveLight.innerHTML = `<i></i>${light.label}`;

      if (!cvReady) {
        updateLiveReady(false, light.ok, 'Preparando o leitor…');
        return;
      }

      src = cv.imread(sample);
      const candidates = contourCenters(src);
      try {
        const liveFrame = pickMarkerFrame(candidates, src.cols, src.rows);
        const geometry = frameGeometryMetrics(liveFrame, src.cols, src.rows);
        drawGeometryOverlay(sample, liveFrame, geometry, light.ok);
        updateLiveReady(true, light.ok, liveFrame.loose ? 'Marcadores detectados • segure firme' : (light.ok ? '' : 'Melhore a iluminação'), liveFrame, geometry);
      } catch (_) {
        clearGeometryOverlay();
        updateLiveReady(false, light.ok, 'Enquadre os marcadores do bloco');
      }
    } catch (err) {
      console.warn('Pré-validação da câmera falhou', err);
      updateLiveReady(false, false, 'Ajuste a câmera');
    } finally {
      src?.delete();
      liveDetectBusy = false;
    }
  }

  function startLiveDetection() {
    stopLiveDetection();
    resetLiveUi();
    checkLiveFrame();
    liveDetectTimer = setInterval(checkLiveFrame, 520);
  }

  async function prepareCameraTrack() {
    const track = cameraStream?.getVideoTracks?.()[0];
    if (!track) return;
    try {
      const caps = track.getCapabilities?.() || {};
      if (Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
        await track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
      }
      if (caps.torch && cameraTorch) {
        cameraTorch.hidden = false;
      } else if (cameraTorch) {
        cameraTorch.hidden = true;
      }
    } catch (err) {
      console.warn('Ajustes extras da câmera não foram aplicados.', err);
    }
  }

  function mountScannerFullscreen() {
    if (!liveCamera) return;

    if (!scannerHome) {
      scannerHome = liveCamera.parentNode;
      scannerPlaceholder = document.createComment('gabarito-online-scanner-home');
      scannerHome.insertBefore(scannerPlaceholder, liveCamera);
    }

    // Move o scanner para o body para escapar de cards/animacoes com transform,
    // que podem limitar position:fixed em navegadores mobile.
    if (liveCamera.parentNode !== document.body) {
      document.body.appendChild(liveCamera);
    }

    liveCamera.hidden = false;
    liveCamera.classList.add('browser-scanner-fullscreen');
    document.documentElement.classList.add('browser-scanner-open');
    document.body.classList.add('browser-scanner-open');
  }

  function restoreScannerHome() {
    if (!liveCamera) return;
    liveCamera.classList.remove('browser-scanner-fullscreen');

    if (scannerHome && scannerPlaceholder && scannerPlaceholder.parentNode === scannerHome) {
      scannerHome.insertBefore(liveCamera, scannerPlaceholder.nextSibling);
      scannerPlaceholder.remove();
      scannerPlaceholder = null;
      scannerHome = null;
    }

    document.documentElement.classList.remove('browser-scanner-open');
    document.body.classList.remove('browser-scanner-open');
    clearGeometryOverlay();
  }

  function requestNativeFullscreen() {
    if (!liveCamera || fullscreenRequested) return;
    fullscreenRequested = true;

    try {
      const request = liveCamera.requestFullscreen || liveCamera.webkitRequestFullscreen;
      if (typeof request === 'function') {
        const result = request.call(liveCamera, { navigationUI: 'hide' });
        if (result && typeof result.catch === 'function') {
          result.catch(() => { fullscreenRequested = false; });
        }
      } else {
        fullscreenRequested = false;
      }
    } catch (_) {
      fullscreenRequested = false;
    }
  }

  async function exitNativeFullscreen() {
    try {
      if (document.fullscreenElement && document.exitFullscreen) {
        await document.exitFullscreen();
      } else if (document.webkitFullscreenElement && document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    } catch (_) {}
    fullscreenRequested = false;
  }

  async function startCamera() {
    clearScannerDiagnostic();

    if (!window.isSecureContext) {
      const msg = 'A câmera do navegador exige HTTPS. Abra o sistema pelo endereço https:// e tente novamente.';
      setStatus('HTTPS necessário');
      showScannerDiagnostic(msg);
      alert(msg);
      return;
    }

    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
      const msg = 'Este navegador não disponibilizou a câmera para o site. Use Chrome, Edge ou Safari atualizado e confira a permissão de câmera.';
      setStatus('Câmera indisponível');
      showScannerDiagnostic(msg);
      alert(msg);
      return;
    }

    stopCamera();
    resetLiveUi();

    // Mostra o scanner imediatamente em uma camada realmente independente da pagina.
    // O elemento vai para o body para nao ficar preso a cards/animacoes transformadas.
    captureChoices.hidden = true;
    mountScannerFullscreen();
    requestNativeFullscreen();
    setStatus('Abrindo scanner…');
    setLiveMessage('Solicitando permissão da câmera…');

    try {
      cameraStream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1600, max: 1920 },
          height: { ideal: 1200, max: 1440 }
        }
      });

      cameraVideo.srcObject = cameraStream;
      await cameraVideo.play();
      await prepareCameraTrack();
      clearScannerDiagnostic();
      setStatus('Scanner aberto', true);
      setLiveMessage('Preparando reconhecimento…');

      if (!cvReady) {
        waitForCv().then(() => {
          if (cameraStream) startLiveDetection();
        }).catch((err) => {
          console.error(err);
          if (cameraStream) {
            setLiveMessage('Câmera aberta • leitor ainda não carregou');
            showScannerDiagnostic('A câmera abriu, mas o módulo de leitura não carregou. Verifique sua conexão e recarregue a página.');
          }
        });
      } else {
        startLiveDetection();
      }
    } catch (err) {
      console.error('Erro getUserMedia:', err);
      let msg = 'Não consegui acessar a câmera.';
      if (err && (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError')) {
        msg = 'Permissão de câmera negada. Toque no cadeado ao lado do endereço do site, permita a câmera e tente novamente.';
      } else if (err && (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError')) {
        msg = 'Nenhuma câmera foi encontrada neste dispositivo.';
      } else if (err && (err.name === 'NotReadableError' || err.name === 'TrackStartError')) {
        msg = 'A câmera está sendo usada por outro aplicativo. Feche outros apps que usam a câmera e tente novamente.';
      } else if (err && err.name === 'OverconstrainedError') {
        msg = 'A câmera não aceitou a configuração solicitada. Recarregue a página para tentar em modo compatível.';
      }
      setStatus('Não foi possível abrir a câmera');
      stopCamera();
      liveCamera.hidden = true;
      restoreScannerHome();
      exitNativeFullscreen();
      captureChoices.hidden = false;
      showScannerDiagnostic(msg);
      alert(msg);
    }
  }

  function stopCamera() {
    stopLiveDetection();
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
      cameraStream = null;
    }
    if (cameraVideo) cameraVideo.srcObject = null;
    torchEnabled = false;
    if (cameraTorch) {
      cameraTorch.classList.remove('active');
      cameraTorch.hidden = true;
    }
    document.documentElement.classList.remove('browser-scanner-open');
    document.body.classList.remove('browser-scanner-open');
  }

  const onFullscreenChange = () => {
    const active = document.fullscreenElement || document.webkitFullscreenElement;
    if (!active) fullscreenRequested = false;
  };
  document.addEventListener('fullscreenchange', onFullscreenChange);
  document.addEventListener('webkitfullscreenchange', onFullscreenChange);

  async function toggleTorch() {
    const track = cameraStream?.getVideoTracks?.()[0];
    if (!track) return;
    try {
      torchEnabled = !torchEnabled;
      await track.applyConstraints({ advanced: [{ torch: torchEnabled }] });
      cameraTorch?.classList.toggle('active', torchEnabled);
    } catch (err) {
      torchEnabled = false;
      cameraTorch?.classList.remove('active');
      console.warn('Lanterna não disponível.', err);
    }
  }

  async function captureFromCamera() {
    if (!cameraVideo.videoWidth || !cameraVideo.videoHeight) {
      alert('A câmera ainda está iniciando. Aguarde um instante e tente novamente.');
      return;
    }
    if (cameraCapture?.disabled) return;
    cameraCapture.disabled = true;
    cameraCapture.classList.add('loading');
    if (captureLabel) captureLabel.textContent = 'Capturando…';
    resetForNewPhoto();
    drawToMainCanvas(cameraVideo, cameraVideo.videoWidth, cameraVideo.videoHeight);
    stopCamera();
    liveCamera.hidden = true;
    restoreScannerHome();
    await exitNativeFullscreen();
    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    try {
      await processImage();
    } finally {
      cameraCapture.classList.remove('loading');
      if (captureLabel) captureLabel.textContent = 'Capturar e corrigir';
    }
  }

  cameraCapture?.addEventListener('click', captureFromCamera);
  cameraTorch?.addEventListener('click', toggleTorch);
  cameraCancel?.addEventListener('click', async () => {
    stopCamera();
    liveCamera.hidden = true;
    restoreScannerHome();
    await exitNativeFullscreen();
    captureChoices.hidden = false;
    setStatus('Pronto para abrir o scanner', true);
  });
  changePhoto?.addEventListener('click', () => {
    previewWrap.hidden = true;
    scanActions.hidden = true;
    review.hidden = true;
    resultCard.hidden = true;
    startCamera();
  });
  window.addEventListener('pagehide', stopCamera);

  function parsePayload(text) {
    const raw = String(text || '').trim();
    if (!raw) return null;

    // Formato v4.0: continua curto para facilitar a leitura pela câmera,
    // mas inclui um prefixo do token individual do aluno/prova para validação no servidor.
    // GO4:<exam_id>:<student_id>:<token_prefix>
    const secureCompact = raw.match(/^GO4:(\d+):(\d+):([a-f0-9]{8,16})$/i);
    if (secureCompact) {
      return { exam: secureCompact[1], student: secureCompact[2], token: secureCompact[3], format: 'GO4' };
    }

    // Compatibilidade com folhas v2.8/v2.9 já impressas.
    const compact = raw.match(/^GO2:(\d+):(\d+)$/i);
    if (compact) {
      return { exam: compact[1], student: compact[2], token: '', format: 'GO2' };
    }

    // Mantém compatibilidade com folhas antigas já impressas.
    if (!(raw.startsWith('GS1|') || raw.startsWith('GS2|'))) return null;
    const parts = {};
    raw.split('|').slice(1).forEach(part => {
      const idx = part.indexOf('=');
      if (idx > -1) parts[part.slice(0, idx)] = part.slice(idx + 1);
    });
    parts.format = raw.startsWith('GS2|') ? 'GS2' : 'GS1';
    return parts;
  }

  function qrSourceSize(source) {
    return {
      width: Number(source?.width || source?.videoWidth || source?.naturalWidth || 0),
      height: Number(source?.height || source?.videoHeight || source?.naturalHeight || 0)
    };
  }

  function makeQrRegion(source, region = null, targetSide = 900, enhance = false) {
    const { width: sw, height: sh } = qrSourceSize(source);
    if (!sw || !sh) return null;

    const r = region || { x: 0, y: 0, w: 1, h: 1 };
    const sx = Math.max(0, Math.floor(sw * r.x));
    const sy = Math.max(0, Math.floor(sh * r.y));
    const sWidth = Math.max(1, Math.min(sw - sx, Math.floor(sw * r.w)));
    const sHeight = Math.max(1, Math.min(sh - sy, Math.floor(sh * r.h)));

    // Para QR pequeno, aumentar a região ajuda bastante o jsQR.
    // Limitamos o tamanho para não voltar ao problema de memória.
    const longest = Math.max(sWidth, sHeight);
    const scale = Math.min(4, Math.max(0.6, targetSide / longest));
    const out = document.createElement('canvas');
    out.width = Math.max(1, Math.round(sWidth * scale));
    out.height = Math.max(1, Math.round(sHeight * scale));
    const ctx = out.getContext('2d', { willReadFrequently: true, alpha: false });
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    if (enhance && 'filter' in ctx) ctx.filter = 'grayscale(100%) contrast(155%)';
    ctx.drawImage(source, sx, sy, sWidth, sHeight, 0, 0, out.width, out.height);
    if (enhance && 'filter' in ctx) ctx.filter = 'none';
    return out;
  }

  function decodeQrCanvas(qrCanvas) {
    if (!qrCanvas?.width || !qrCanvas?.height || typeof window.jsQR !== 'function') return null;
    const qctx = qrCanvas.getContext('2d', { willReadFrequently: true, alpha: false });
    const image = qctx.getImageData(0, 0, qrCanvas.width, qrCanvas.height);
    return window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'attemptBoth' }) || null;
  }

  async function tryIdentifyQr(options = {}) {
    try {
      if (window.GradeScanVendors?.ensureJsQR) await window.GradeScanVendors.ensureJsQR();
      const qrSource = options.source || canvas;
      if (typeof window.jsQR !== 'function' || !qrSource.width || !qrSource.height) return false;

      const aligned = Boolean(options.aligned);
      const regions = aligned
        ? [
            // Depois do alinhamento do bloco v2.9, o QR fica na caixa do aluno, no topo à direita.
            { x: 0.66, y: 0.13, w: 0.23, h: 0.20 },
            { x: 0.58, y: 0.09, w: 0.33, h: 0.28 },
            { x: 0.00, y: 0.00, w: 1.00, h: 0.40 },
            null
          ]
        : [
            null,
            { x: 0.00, y: 0.00, w: 1.00, h: 0.55 }
          ];

      for (const region of regions) {
        // Primeiro imagem normal, depois uma versão com contraste reforçado.
        for (const enhance of [false, true]) {
          const qrCanvas = makeQrRegion(qrSource, region, aligned ? 1050 : QR_MAX_SIDE, enhance);
          if (!qrCanvas) continue;
          const result = decodeQrCanvas(qrCanvas);
          if (!result?.data) continue;

          const data = parsePayload(result.data);
          if (!data || Number(data.exam) !== examId) continue;
          const student = findStudent(Number(data.student));
          if (!student) continue;

          showStudent(student, data.token || '');
          setStatus(`Aluno identificado: ${student.name}`, true);
          return true;
        }
      }
    } catch (err) {
      console.warn('QR não reconhecido', err);
    }
    return false;
  }

  function pointDistance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
  }

  function midpoint(a, b) {
    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
  }

  function polygonArea(points) {
    let area = 0;
    for (let i = 0; i < points.length; i++) {
      const a = points[i];
      const b = points[(i + 1) % points.length];
      area += a.x * b.y - b.x * a.y;
    }
    return Math.abs(area) / 2;
  }

  function contourCenters(src) {
    const gray = new cv.Mat();
    const blur = new cv.Mat();
    const bin = new cv.Mat();
    const contours = new cv.MatVector();
    const hierarchy = new cv.Mat();
    const candidates = [];
    try {
      cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY);
      cv.GaussianBlur(gray, blur, new cv.Size(5, 5), 0);
      cv.adaptiveThreshold(blur, bin, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY_INV, 51, 11);
      cv.findContours(bin, contours, hierarchy, cv.RETR_LIST, cv.CHAIN_APPROX_SIMPLE);
      const imgArea = src.rows * src.cols;

      for (let i = 0; i < contours.size(); i++) {
        const c = contours.get(i);
        const area = cv.contourArea(c);
        if (area < imgArea * 0.00018 || area > imgArea * 0.02) {
          c.delete();
          continue;
        }

        const peri = cv.arcLength(c, true);
        const approx = new cv.Mat();
        cv.approxPolyDP(c, approx, 0.045 * peri, true);

        if (approx.rows === 4 && cv.isContourConvex(approx)) {
          const rect = cv.boundingRect(approx);
          const extent = area / Math.max(1, rect.width * rect.height);
          const pts = [];
          for (let r = 0; r < 4; r++) {
            const ptr = approx.intPtr(r, 0);
            pts.push({ x: ptr[0], y: ptr[1] });
          }
          const sides = pts.map((pt, idx) => pointDistance(pt, pts[(idx + 1) % 4])).filter(v => v > 0);
          const minSide = Math.min(...sides);
          const maxSide = Math.max(...sides);
          const sideRatio = maxSide / Math.max(1, minSide);
          const axisRatio = rect.width / Math.max(1, rect.height);

          if (extent > 0.43 && sideRatio < 2.6 && axisRatio > 0.42 && axisRatio < 2.4) {
            candidates.push({
              x: rect.x + rect.width / 2,
              y: rect.y + rect.height / 2,
              size: Math.sqrt(area),
              area
            });
          }
        }
        approx.delete();
        c.delete();
      }
    } finally {
      gray.delete();
      blur.delete();
      bin.delete();
      contours.delete();
      hierarchy.delete();
    }
    candidates.sort((a, b) => b.area - a.area);
    const unique = [];
    for (const candidate of candidates) {
      const duplicated = unique.some(existing => pointDistance(existing, candidate) < Math.max(7, Math.min(existing.size, candidate.size) * 0.38));
      if (!duplicated) unique.push(candidate);
    }
    return unique;
  }

  function orderQuad(points) {
    const bySumAsc = [...points].sort((a, b) => (a.x + a.y) - (b.x + b.y));
    const byDiffAsc = [...points].sort((a, b) => (a.x - a.y) - (b.x - b.y));
    const tl = bySumAsc[0];
    const br = bySumAsc[bySumAsc.length - 1];
    const bl = byDiffAsc[0];
    const tr = byDiffAsc[byDiffAsc.length - 1];
    return [tl, tr, br, bl];
  }

  function frameScore(corners, mids, w, h) {
    const [tl, tr, br, bl] = corners;
    const area = polygonArea(corners);
    const top = pointDistance(tl, tr);
    const bottom = pointDistance(bl, br);
    const left = pointDistance(tl, bl);
    const right = pointDistance(tr, br);
    const minSide = Math.min(top, bottom, left, right);
    const maxSide = Math.max(top, bottom, left, right);
    if (area < w * h * 0.018 || minSide < Math.min(w, h) * 0.09 || maxSide / Math.max(1, minSide) > 3.8) return -1;
    const diag1 = pointDistance(tl, br);
    const diag2 = pointDistance(tr, bl);
    const diagBalance = Math.min(diag1, diag2) / Math.max(1, Math.max(diag1, diag2));
    const sideBalance = Math.min(top, bottom) / Math.max(1, Math.max(top, bottom)) + Math.min(left, right) / Math.max(1, Math.max(left, right));
    const areaScore = Math.min(3, area / (w * h * 0.05));
    return areaScore + sideBalance + diagBalance + (mids ? 2.2 : 0);
  }

  function pickMarkerFrame(candidates, w, h) {
    if (candidates.length < 5) {
      throw new Error('Não encontrei marcadores suficientes do gabarito. Reimprima o formulário atualizado e fotografe o bloco inteiro.');
    }

    const shortlist = candidates.slice(0, Math.min(candidates.length, 12));

    const nearest = (target, pool) => {
      let best = null;
      let dist = Infinity;
      for (const c of pool) {
        const d = pointDistance(c, target);
        if (d < dist) { dist = d; best = c; }
      }
      return { best, dist };
    };

    let bestFrame = null;
    let bestScore = -1;

    for (let i = 0; i < shortlist.length - 3; i++) {
      for (let j = i + 1; j < shortlist.length - 2; j++) {
        for (let k = j + 1; k < shortlist.length - 1; k++) {
          for (let l = k + 1; l < shortlist.length; l++) {
            const corners = orderQuad([shortlist[i], shortlist[j], shortlist[k], shortlist[l]]);
            if (new Set(corners).size !== 4) continue;
            const [tl, tr, br, bl] = corners;
            const area = polygonArea(corners);
            if (area < w * h * 0.018) continue;

            const top = pointDistance(tl, tr);
            const bottom = pointDistance(bl, br);
            const left = pointDistance(tl, bl);
            const right = pointDistance(tr, br);
            if (Math.min(top, bottom, left, right) < Math.min(w, h) * 0.09) continue;

            const cornerSet = new Set(corners);
            const remaining = shortlist.filter(c => !cornerSet.has(c));
            const leftMid = midpoint(tl, bl);
            const rightMid = midpoint(tr, br);
            const leftTol = Math.max(18, left * 0.18);
            const rightTol = Math.max(18, right * 0.18);
            const lm = nearest(leftMid, remaining);
            const rm = nearest(rightMid, remaining.filter(c => c !== lm.best));
            const leftOk = !!lm.best && lm.dist <= leftTol;
            const rightOk = !!rm.best && rm.dist <= rightTol;
            const midsOk = leftOk && rightOk;

            let ml = leftOk ? lm.best : leftMid;
            let mr = rightOk ? rm.best : rightMid;
            const score = frameScore(corners, midsOk, w, h) - (midsOk ? 0 : 0.55);
            if (score > bestScore) {
              bestScore = score;
              bestFrame = { tl, tr, br, bl, ml, mr, estimatedMid: !midsOk };
            }
          }
        }
      }
    }

    if (!bestFrame) {
      throw new Error('Não consegui validar o formato do bloco do gabarito. Fotografe mais de frente, sem cortar os cantos e com os marcadores visíveis.');
    }

    if (bestFrame.estimatedMid) {
      bestFrame.loose = true;
    }
    return bestFrame;
  }

  function mmXFor(mm, width) { return mm / TEMPLATE_W_MM * width; }
  function mmYFor(mm, height) { return mm / TEMPLATE_H_MM * height; }
  function mmX(mm) { return mmXFor(mm, NORMALIZED_W); }
  function mmY(mm) { return mmYFor(mm, NORMALIZED_H); }

  function sourceOrderForRotation(frame, rotation) {
    const imageOrder = [frame.tl, frame.tr, frame.br, frame.bl];
    if (rotation === 1) return [imageOrder[1], imageOrder[2], imageOrder[3], imageOrder[0]];
    if (rotation === 2) return [imageOrder[2], imageOrder[3], imageOrder[0], imageOrder[1]];
    if (rotation === 3) return [imageOrder[3], imageOrder[0], imageOrder[1], imageOrder[2]];
    return imageOrder;
  }

  function warpFromMarkers(src, frame, rotation, width, height) {
    const ordered = sourceOrderForRotation(frame, rotation);
    const srcPts = cv.matFromArray(4, 1, cv.CV_32FC2, [
      ordered[0].x, ordered[0].y,
      ordered[1].x, ordered[1].y,
      ordered[2].x, ordered[2].y,
      ordered[3].x, ordered[3].y
    ]);
    const dstPts = cv.matFromArray(4, 1, cv.CV_32FC2, [
      mmXFor(7, width), mmYFor(7, height),
      mmXFor(TEMPLATE_W_MM - 7, width), mmYFor(7, height),
      mmXFor(TEMPLATE_W_MM - 7, width), mmYFor(TEMPLATE_H_MM - 7, height),
      mmXFor(7, width), mmYFor(TEMPLATE_H_MM - 7, height)
    ]);
    const matrix = cv.getPerspectiveTransform(srcPts, dstPts);
    const warped = new cv.Mat();
    cv.warpPerspective(src, warped, matrix, new cv.Size(width, height), cv.INTER_LINEAR, cv.BORDER_CONSTANT, new cv.Scalar(255, 255, 255, 255));
    srcPts.delete();
    dstPts.delete();
    matrix.delete();
    return warped;
  }

  function bubbleLayout(width, height) {
    const rows = Math.ceil(questionCount / 2);
    const spacing = Math.min(9.2, 110 / Math.max(1, rows - 1));
    const bubbleMm = rows > 18 ? 4.1 : (rows > 12 ? 4.8 : 5.5);
    const radius = mmXFor(bubbleMm, width) / 2;
    const points = [];
    for (let q = 1; q <= questionCount; q++) {
      const right = q > rows;
      const idx = right ? q - rows - 1 : q - 1;
      const top = 78 + idx * spacing;
      const base = right ? 110 : 35;
      const cy = mmYFor(top + bubbleMm / 2, height);
      for (let i = 0; i < 5; i++) {
        points.push({
          question: q,
          option: i,
          cx: mmXFor(base + i * 10 + bubbleMm / 2, width),
          cy,
          radius
        });
      }
    }
    return { rows, spacing, bubbleMm, radius, points };
  }

  function annulusMean(gray, cx, cy, r1, r2) {
    const x0 = Math.max(0, Math.floor(cx - r2));
    const x1 = Math.min(gray.cols - 1, Math.ceil(cx + r2));
    const y0 = Math.max(0, Math.floor(cy - r2));
    const y1 = Math.min(gray.rows - 1, Math.ceil(cy + r2));
    let sum = 0;
    let total = 0;
    const r1Sq = r1 * r1;
    const r2Sq = r2 * r2;
    for (let y = y0; y <= y1; y++) {
      const row = gray.ucharPtr(y);
      for (let x = x0; x <= x1; x++) {
        const dx = x - cx;
        const dy = y - cy;
        const d2 = dx * dx + dy * dy;
        if (d2 >= r1Sq && d2 <= r2Sq) {
          sum += row[x];
          total++;
        }
      }
    }
    return total ? sum / total : 255;
  }

  function diskMean(gray, cx, cy, radius) {
    const x0 = Math.max(0, Math.floor(cx - radius));
    const x1 = Math.min(gray.cols - 1, Math.ceil(cx + radius));
    const y0 = Math.max(0, Math.floor(cy - radius));
    const y1 = Math.min(gray.rows - 1, Math.ceil(cy + radius));
    let sum = 0;
    let total = 0;
    const rSq = radius * radius;
    for (let y = y0; y <= y1; y++) {
      const row = gray.ucharPtr(y);
      for (let x = x0; x <= x1; x++) {
        const dx = x - cx;
        const dy = y - cy;
        if (dx * dx + dy * dy <= rSq) {
          sum += row[x];
          total++;
        }
      }
    }
    return total ? sum / total : 255;
  }

  function bubbleRingContrast(gray, cx, cy, radius) {
    const ring = annulusMean(gray, cx, cy, radius * 0.72, radius * 1.10);
    const outside = annulusMean(gray, cx, cy, radius * 1.25, radius * 1.52);
    return Math.max(0, (outside - ring) / Math.max(1, outside));
  }

  function diskDarkFraction(gray, cx, cy, radius, threshold) {
    const x0 = Math.max(0, Math.floor(cx - radius));
    const x1 = Math.min(gray.cols - 1, Math.ceil(cx + radius));
    const y0 = Math.max(0, Math.floor(cy - radius));
    const y1 = Math.min(gray.rows - 1, Math.ceil(cy + radius));
    let dark = 0;
    let total = 0;
    const rSq = radius * radius;
    for (let y = y0; y <= y1; y++) {
      const row = gray.ucharPtr(y);
      for (let x = x0; x <= x1; x++) {
        const dx = x - cx;
        const dy = y - cy;
        if (dx * dx + dy * dy <= rSq) {
          if (row[x] < threshold) dark++;
          total++;
        }
      }
    }
    return total ? dark / total : 0;
  }

  function bubbleFillContrast(gray, cx, cy, radius) {
    // Mede somente o miolo da bolha para ignorar a borda impressa.
    // O limiar de preto é calculado a partir do próprio papel ao redor,
    // portanto funciona melhor com sombras e exposições diferentes entre celulares.
    const innerRadius = Math.max(3, radius * 0.52);
    const inner = diskMean(gray, cx, cy, innerRadius);
    const outside = annulusMean(gray, cx, cy, radius * 1.16, radius * 1.48);
    const rawContrast = Math.max(0, (outside - inner) / Math.max(1, outside));
    const localThreshold = Math.max(35, outside - Math.max(28, outside * 0.16));
    const darkFraction = diskDarkFraction(gray, cx, cy, innerRadius, localThreshold);
    const contrast = Math.min(1, rawContrast * 0.70 + darkFraction * 0.30);
    return { contrast, rawContrast, darkFraction, inner, outside };
  }

  function bubbleGridScore(warped) {
    const gray = new cv.Mat();
    try {
      cv.cvtColor(warped, gray, cv.COLOR_RGBA2GRAY);
      const layout = bubbleLayout(warped.cols, warped.rows);
      const scores = [];
      const stride = layout.points.length > 120 ? 2 : 1;
      for (let i = 0; i < layout.points.length; i += stride) {
        const p = layout.points[i];
        scores.push(bubbleRingContrast(gray, p.cx, p.cy, p.radius));
      }
      return scores.length ? scores.reduce((a, b) => a + b, 0) / scores.length : 0;
    } finally {
      gray.delete();
    }
  }

  function chooseOrientation(src, frame) {
    const candidates = [];
    const testW = 560;
    const testH = 792;
    for (let rotation = 0; rotation < 4; rotation++) {
      const test = warpFromMarkers(src, frame, rotation, testW, testH);
      try {
        candidates.push({ rotation, score: bubbleGridScore(test) });
      } finally {
        test.delete();
      }
    }
    candidates.sort((a, b) => b.score - a.score);
    const best = candidates[0];
    const second = candidates[1] || { score: 0 };
    if (!best || best.score < 0.045) {
      throw new Error('Os marcadores apareceram, mas a grade do bloco do gabarito não ficou alinhada. Fotografe novamente mais de frente e com melhor iluminação.');
    }
    if (best.score < 0.11 && best.score - second.score < 0.015) {
      throw new Error('Não consegui determinar com segurança a orientação do bloco do gabarito. Deixe todo o bloco visível e tente novamente.');
    }
    return { rotation: best.rotation, score: best.score, alternatives: candidates };
  }

  function calibrateOffsetForPoints(gray, points, radius) {
    let best = { dx: 0, dy: 0, score: -1 };
    const maxOffset = Math.max(4, Math.round(radius * 0.46));
    const step = Math.max(2, Math.round(maxOffset / 3));
    const sampled = points.filter((_, idx) => idx % (points.length > 70 ? 3 : 2) === 0);

    for (let dy = -maxOffset; dy <= maxOffset; dy += step) {
      for (let dx = -maxOffset; dx <= maxOffset; dx += step) {
        let sum = 0;
        let total = 0;
        for (const pt of sampled) {
          sum += bubbleRingContrast(gray, pt.cx + dx, pt.cy + dy, pt.radius);
          total++;
        }
        const score = total ? sum / total : 0;
        if (score > best.score) best = { dx, dy, score };
      }
    }
    return best;
  }

  function calibrateOffsets(gray) {
    const layout = bubbleLayout(gray.cols, gray.rows);
    const centerX = gray.cols / 2;
    const leftPoints = layout.points.filter(p => p.cx < centerX);
    const rightPoints = layout.points.filter(p => p.cx >= centerX);
    return {
      left: calibrateOffsetForPoints(gray, leftPoints, layout.radius),
      right: calibrateOffsetForPoints(gray, rightPoints, layout.radius),
      layout
    };
  }

  function median(values) {
    const sorted = [...values].filter(Number.isFinite).sort((a, b) => a - b);
    if (!sorted.length) return 0;
    const mid = Math.floor(sorted.length / 2);
    return sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
  }

  function percentile(values, p) {
    const sorted = [...values].filter(Number.isFinite).sort((a, b) => a - b);
    if (!sorted.length) return 0;
    const idx = Math.max(0, Math.min(sorted.length - 1, (sorted.length - 1) * p));
    const lo = Math.floor(idx);
    const hi = Math.ceil(idx);
    if (lo === hi) return sorted[lo];
    const t = idx - lo;
    return sorted[lo] * (1 - t) + sorted[hi] * t;
  }

  function mad(values, med = median(values)) {
    return median(values.map(v => Math.abs(v - med)));
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function robustBubbleMetric(gray, cx, cy, radius) {
    const d = Math.max(1, radius * 0.10);
    const offsets = [
      [0, 0], [-d, 0], [d, 0], [0, -d], [0, d]
    ];
    const samples = offsets.map(([dx, dy]) => bubbleFillContrast(gray, cx + dx, cy + dy, radius));
    return {
      contrast: median(samples.map(s => s.contrast)),
      rawContrast: median(samples.map(s => s.rawContrast)),
      darkFraction: median(samples.map(s => s.darkFraction)),
      inner: median(samples.map(s => s.inner)),
      outside: median(samples.map(s => s.outside))
    };
  }

  function imageSharpnessScore(warped) {
    const gray = new cv.Mat();
    const small = new cv.Mat();
    try {
      cv.cvtColor(warped, gray, cv.COLOR_RGBA2GRAY);
      const scale = Math.min(1, 520 / Math.max(gray.cols, gray.rows));
      const targetW = Math.max(80, Math.round(gray.cols * scale));
      const targetH = Math.max(80, Math.round(gray.rows * scale));
      cv.resize(gray, small, new cv.Size(targetW, targetH), 0, 0, cv.INTER_AREA);
      let sum = 0;
      let total = 0;
      const stride = Math.max(1, Math.round(Math.min(targetW, targetH) / 220));
      for (let y = stride; y < targetH - stride; y += stride) {
        const row = small.ucharPtr(y);
        const prev = small.ucharPtr(y - stride);
        for (let x = stride; x < targetW - stride; x += stride) {
          const gx = Math.abs(row[x + stride] - row[x - stride]);
          const gy = Math.abs(row[x] - prev[x]);
          sum += gx + gy;
          total++;
        }
      }
      const gradient = total ? sum / total : 0;
      return {
        gradient,
        score: Math.round(clamp((gradient - 8) / 20 * 100, 0, 100))
      };
    } finally {
      gray.delete();
      small.delete();
    }
  }

  function readBubbles(warped, alignmentScore) {
    const gray = new cv.Mat();
    cv.cvtColor(warped, gray, cv.COLOR_RGBA2GRAY);
    const calibration = calibrateOffsets(gray);
    const layout = calibration.layout;
    const letters = ['A', 'B', 'C', 'D', 'E'];
    const answers = [];

    try {
      const metricsByQuestion = new Map();
      const allScores = [];

      for (let q = 1; q <= questionCount; q++) {
        const points = layout.points.filter(p => p.question === q);
        const sideOffset = q > layout.rows ? calibration.right : calibration.left;
        const metrics = points.map(pt => robustBubbleMetric(
          gray,
          pt.cx + sideOffset.dx,
          pt.cy + sideOffset.dy,
          pt.radius
        ));
        metricsByQuestion.set(q, metrics);
        metrics.forEach(m => allScores.push(m.contrast));
      }

      // Como cada questão possui 5 alternativas e normalmente no máximo 1 está marcada,
      // a maioria absoluta das bolhas é vazia. Usamos essa maioria para aprender o nível
      // de "bolha vazia" da própria foto em vez de depender de um limiar fixo.
      const globalMedian = median(allScores);
      const globalMad = Math.max(0.004, mad(allScores, globalMedian));
      const p75 = percentile(allScores, 0.75);
      const adaptiveFloor = clamp(
        Math.max(globalMedian + Math.max(0.035, globalMad * 3.1), p75 + 0.012),
        0.067,
        0.17
      );
      const separationFloor = clamp(Math.max(0.022, globalMad * 1.35), 0.022, 0.060);
      const excessFloor = clamp(Math.max(0.034, globalMad * 1.9), 0.034, 0.075);

      for (let q = 1; q <= questionCount; q++) {
        const metrics = metricsByQuestion.get(q) || [];
        const scores = metrics.map(m => m.contrast);
        const ranked = scores.map((v, i) => ({ v, i })).sort((a, b) => b.v - a.v);
        const best = ranked[0] || { v: 0, i: 0 };
        const second = ranked[1] || { v: 0, i: 1 };
        const localBaseline = median(scores);
        const excess = Math.max(0, best.v - localBaseline);
        const separation = Math.max(0, best.v - second.v);
        const secondExcess = Math.max(0, second.v - localBaseline);
        const z = (best.v - globalMedian) / globalMad;

        const strong = best.v >= adaptiveFloor && excess >= excessFloor && separation >= separationFloor;
        const veryStrong = best.v >= Math.max(0.12, adaptiveFloor * 1.14) && excess >= Math.max(0.045, excessFloor * 0.9) && separation >= Math.max(0.018, separationFloor * 0.82);
        const secondLooksMarked = second.v >= Math.max(0.070, adaptiveFloor * 0.88) && secondExcess >= Math.max(0.030, excessFloor * 0.82);
        const doubleMarked = secondLooksMarked && second.v / Math.max(best.v, 0.001) > 0.66;
        const weakSignal = best.v >= adaptiveFloor * 0.76 || excess >= excessFloor * 0.78;

        let answer = null;
        let ambiguous = false;
        let reason = 'blank';
        if ((strong || veryStrong) && !doubleMarked) {
          answer = letters[best.i];
          reason = 'marked';
        } else if (doubleMarked) {
          ambiguous = true;
          reason = 'double';
        } else if (weakSignal) {
          ambiguous = true;
          answer = best.v >= adaptiveFloor * 0.88 ? letters[best.i] : null;
          reason = 'weak';
        }

        let conf;
        if (answer && !ambiguous) {
          const alignBoost = clamp(alignmentScore * 45, 0, 10);
          conf = Math.round(clamp(54 + z * 6.0 + separation * 260 + excess * 135 + alignBoost, 0, 99));
          if (conf < 78) {
            ambiguous = true;
            reason = 'low-confidence';
          }
        } else if (ambiguous) {
          conf = Math.round(clamp(32 + separation * 120 + Math.max(0, z) * 2, 25, 68));
        } else {
          // Branco só é aceito quando nenhuma alternativa ultrapassa o ruído aprendido.
          const blankMargin = adaptiveFloor - best.v;
          conf = Math.round(clamp(80 + blankMargin * 120, 78, 98));
        }

        answers.push({
          question: q,
          answer,
          confidence: conf,
          ratios: scores.map(v => Number(v.toFixed(4))),
          ambiguous,
          reason,
          diagnostic: {
            globalMedian: Number(globalMedian.toFixed(4)),
            globalMad: Number(globalMad.toFixed(4)),
            adaptiveFloor: Number(adaptiveFloor.toFixed(4)),
            localBaseline: Number(localBaseline.toFixed(4)),
            excess: Number(excess.toFixed(4)),
            separation: Number(separation.toFixed(4)),
            z: Number(z.toFixed(2))
          }
        });
      }

      const safe = answers.filter(a => !a.ambiguous);
      const average = safe.length ? safe.reduce((sum, a) => sum + a.confidence, 0) / safe.length : 0;
      return {
        answers,
        confidence: Math.round(average),
        calibration,
        alignmentScore,
        thresholds: { globalMedian, globalMad, adaptiveFloor, separationFloor, excessFloor }
      };
    } finally {
      gray.delete();
    }
  }

  function alignmentGridPercent(score) {
    return Math.max(0, Math.min(100, Math.round((score - 0.025) / 0.115 * 100)));
  }

  function showAlignmentPreview(meta) {
    if (!alignmentPreview) return;
    pendingAlignment = meta;
    if (alignmentPreview.parentNode !== document.body) document.body.appendChild(alignmentPreview);
    alignmentPreview.hidden = false;
    document.documentElement.classList.add('alignment-preview-open');
    document.body.classList.add('alignment-preview-open');

    const gridPct = alignmentGridPercent(meta.alignmentScore);
    const sharpness = Math.max(0, Math.min(100, Math.round(meta.sharpnessScore ?? 0)));
    const combined = Math.round(meta.geometryScore * 0.30 + gridPct * 0.45 + sharpness * 0.25 - (meta.loose ? 7 : 0));
    const quality = Math.max(0, Math.min(100, combined));
    const grade = quality >= 82 ? 'Excelente' : (quality >= 67 ? 'Boa' : 'Aceitável');
    alignmentBadge.textContent = `${quality}%`;
    alignmentBadge.className = `alignment-quality-badge ${quality >= 82 ? 'excellent' : (quality >= 67 ? 'good' : 'warning')}`;
    alignmentStatus.textContent = `Alinhamento ${grade.toLowerCase()}`;
    alignmentGeometry.textContent = `${meta.geometryScore}%`;
    alignmentGrid.textContent = `${gridPct}%`;
    alignmentPerspective.textContent = `${Math.round(meta.perspective * 100)}%`;
    if (alignmentSharpness) alignmentSharpness.textContent = `${sharpness}%`;
    alignmentMessage.textContent = quality >= 82
      ? 'O bloco foi corrigido com excelente geometria. Você pode continuar.'
      : (quality >= 67
        ? (sharpness >= 52 ? 'O alinhamento está bom. Confira visualmente se todas as bolhas aparecem inteiras antes de continuar.' : 'O alinhamento está bom, mas a imagem está um pouco desfocada. Refazer a foto reduz o risco de leitura errada.')
        : 'A captura está no limite de segurança. Para evitar leitura errada, prefira refazer a foto com menos inclinação, mais nitidez e sem cortes.');
    alignmentConfirm.disabled = quality < 66 || sharpness < 42 || (meta.loose && quality < 82);
    setStatus('Confira a prévia do alinhamento');
  }

  function hideAlignmentPreview() {
    if (alignmentPreview) alignmentPreview.hidden = true;
    document.documentElement.classList.remove('alignment-preview-open');
    document.body.classList.remove('alignment-preview-open');
  }

  async function confirmAlignment() {
    if (!pendingAlignment || !alignmentCanvas?.width) return;
    alignmentConfirm.disabled = true;
    alignmentConfirm.classList.add('loading');
    let aligned = null;
    try {
      setStatus('Identificando aluno e lendo respostas…');
      await tryIdentifyQr({ aligned: true, source: alignmentCanvas });
      aligned = cv.imread(alignmentCanvas);
      const finalAlignment = bubbleGridScore(aligned);
      const finalSharpness = imageSharpnessScore(aligned);
      if (finalSharpness.score < 38) {
        throw new Error('A imagem ficou desfocada demais para uma correção segura. Refaça a foto segurando o celular firme.');
      }
      if (finalAlignment < 0.042) {
        throw new Error('A grade de respostas perdeu alinhamento na validação final. Refaça a foto com o bloco inteiro visível.');
      }
      cv.imshow(canvas, aligned);
      const read = readBubbles(aligned, finalAlignment);
      detectedAnswers = read.answers;
      globalConfidence = read.confidence;
      const uncertain = detectedAnswers.filter(a => a.ambiguous).length;
      if (uncertain > Math.max(3, Math.ceil(questionCount * 0.25))) {
        throw new Error(`A leitura ficou insegura em ${uncertain} questões. Para evitar uma nota errada, refaça a foto com melhor iluminação, mais nitidez ou menos inclinação.`);
      }
      if (globalConfidence < 74 && uncertain > 0) {
        throw new Error('A confiança geral da leitura ficou baixa. Refaça a captura em vez de salvar uma correção duvidosa.');
      }
      hideAlignmentPreview();
      pendingAlignment = null;
      renderReview();
      review.hidden = false;
      review.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setStatus(uncertain ? `${uncertain} questão(ões) para revisar` : 'Leitura concluída', !uncertain);
    } catch (err) {
      console.error(err);
      alert(err?.message || 'Não foi possível concluir a leitura. Refaça a captura.');
      setStatus('Revise ou refaça a captura');
    } finally {
      aligned?.delete();
      alignmentConfirm.classList.remove('loading');
      alignmentConfirm.disabled = false;
    }
  }

  alignmentConfirm?.addEventListener('click', confirmAlignment);
  alignmentRetakes.forEach(btn => btn.addEventListener('click', () => {
    pendingAlignment = null;
    hideAlignmentPreview();
    startCamera();
  }));

  async function processImage() {
    if (!canvas.width || !canvas.height) return;
    if (!cvReady) {
      await waitForCv();
      if (!cvReady) {
        alert('O leitor de imagem não ficou disponível. Verifique sua conexão e tente novamente.');
        return;
      }
    }

    processBtn.disabled = true;
    processBtn.classList.add('loading');
    processLabel.textContent = 'Calculando geometria…';
    let src = null;
    let warped = null;

    try {
      await tryIdentifyQr();
      src = cv.imread(canvas);
      const candidates = contourCenters(src);
      const frame = pickMarkerFrame(candidates, src.cols, src.rows);
      const geometry = frameGeometryMetrics(frame, src.cols, src.rows);
      qualityMarkers.textContent = frame.loose ? 'Marcadores: quadro estimado ✓' : 'Marcadores: 6/6 detectados ✓';

      processLabel.textContent = 'Corrigindo perspectiva…';
      const orientation = chooseOrientation(src, frame);
      warped = warpFromMarkers(src, frame, orientation.rotation, NORMALIZED_W, NORMALIZED_H);
      const finalAlignment = bubbleGridScore(warped);
      const sharpness = imageSharpnessScore(warped);
      if (sharpness.score < 32) {
        throw new Error('A captura ficou desfocada. Segure o celular firme, aguarde o foco e fotografe novamente.');
      }
      if (finalAlignment < 0.042) {
        throw new Error('O bloco foi encontrado, mas a grade não ficou alinhada com segurança. Refaça a foto sem cortar os marcadores.');
      }

      cv.imshow(alignmentCanvas, warped);
      qualityMarkers.textContent = `Alinhamento: ${frame.loose ? 'estimado' : '6/6 marcadores'} ✓`;
      showAlignmentPreview({
        alignmentScore: finalAlignment,
        geometryScore: geometry.score,
        perspective: geometry.perspective,
        sharpnessScore: sharpness.score,
        sharpnessGradient: sharpness.gradient,
        loose: Boolean(frame.loose),
        rotation: orientation.rotation
      });
    } catch (err) {
      console.error(err);
      qualityMarkers.textContent = 'Marcadores/alinhamento: verifique a foto';
      const raw = String(err?.message || err || '');
      const memoryError = /memory|mem[oó]ria|allocation|array buffer|out of|abort\(/i.test(raw);
      alert(memoryError
        ? 'O navegador ficou sem memória durante a análise. Feche outras abas e tente novamente.'
        : (err?.message || 'Não foi possível alinhar o gabarito. Tente novamente mantendo o bloco inteiro visível.'));
      setStatus('Nova foto necessária');
    } finally {
      src?.delete();
      warped?.delete();
      processBtn.disabled = false;
      processBtn.classList.remove('loading');
      processLabel.textContent = 'Analisar novamente';
    }
  }

  processBtn.addEventListener('click', processImage);

  function renderReview() {
    confidenceEl.textContent = `${globalConfidence}%`;
    detectedGrid.innerHTML = '';
    const hasAmbiguous = detectedAnswers.some(a => a.ambiguous);
    reviewAlert.hidden = !hasAmbiguous;
    detectedAnswers.forEach(item => {
      const row = document.createElement('div');
      row.className = `detected-row${item.ambiguous ? ' ambiguous' : ''}`;
      const options = ['A','B','C','D','E'].map(letter => `<button type="button" class="detected-option ${item.answer===letter?'selected':''}" data-answer="${letter}">${letter}</button>`).join('');
      const reviewLabel = item.ambiguous ? (item.reason === 'double' ? 'Dupla' : 'Revisar') : item.confidence + '%';
      row.innerHTML = `<span class="detected-q">${String(item.question).padStart(2,'0')}</span><div class="detected-options">${options}<button type="button" class="detected-option blank ${item.answer===null&&!item.ambiguous?'selected':''}" data-answer="">—</button></div><span class="detected-confidence">${reviewLabel}</span>`;
      row.querySelectorAll('[data-answer]').forEach(btn => btn.addEventListener('click', () => {
        item.answer = btn.dataset.answer || null;
        item.ambiguous = false;
        item.confidence = 100;
        row.classList.remove('ambiguous');
        row.querySelectorAll('[data-answer]').forEach(b => b.classList.toggle('selected', b === btn));
        row.querySelector('.detected-confidence').textContent = 'manual';
        reviewAlert.hidden = !detectedAnswers.some(a => a.ambiguous);
      }));
      detectedGrid.appendChild(row);
    });
  }

  async function saveScan(overwrite = false) {
    if (!selectedStudent) {
      alert('Não foi possível identificar o aluno. Selecione o aluno manualmente antes de salvar.');
      manualStudent.focus();
      return;
    }
    if (detectedAnswers.some(a => a.ambiguous)) {
      alert('Revise as questões marcadas como duvidosas antes de salvar.');
      return;
    }
    saveBtn.disabled = true;
    saveBtn.classList.add('loading');
    const fd = new FormData();
    fd.append('csrf_token', window.GRADESCAN.csrf);
    fd.append('exam_id', String(examId));
    fd.append('student_id', String(selectedStudent.id));
    fd.append('token', detectedToken);
    fd.append('overwrite', overwrite ? '1' : '0');
    fd.append('confidence', String(globalConfidence));
    fd.append('answers', JSON.stringify(detectedAnswers.map(a => ({ question: a.question, answer: a.answer || '', confidence: a.confidence }))));
    try {
      const endpoint = `${window.GRADESCAN.baseUrl}api/save_scan.php`;
      const res = await fetch(endpoint, { method: 'POST', body: fd, credentials: 'same-origin' });
      const data = await res.json();
      if (res.status === 409 && data.requires_overwrite) {
        saveBtn.disabled = false;
        saveBtn.classList.remove('loading');
        const current = Number(data.current_score || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
        if (confirm(`Este aluno já possui nota ${current}. Deseja substituir a correção anterior? A alteração ficará registrada na auditoria.`)) {
          return saveScan(true);
        }
        return;
      }
      if (!res.ok || !data.ok) throw new Error(data.message || 'Não foi possível salvar a correção.');
      selectedStudent.corrected = 1;
      selectedStudent.score = data.score;
      showResult(data);
    } catch (err) {
      alert(err.message || 'Falha ao salvar a correção.');
    } finally {
      saveBtn.disabled = false;
      saveBtn.classList.remove('loading');
    }
  }
  saveBtn.addEventListener('click', () => saveScan(false));

  function showResult(data) {
    review.hidden = true;
    document.querySelector('.scanner-layout').hidden = true;
    resultCard.hidden = false;
    resultCard.querySelector('[data-result-status]').textContent = data.updated ? 'CORREÇÃO ATUALIZADA' : 'CORREÇÃO CONCLUÍDA';
    resultCard.querySelector('[data-result-name]').textContent = data.student.name;
    resultCard.querySelector('[data-result-enrollment]').textContent = `Matrícula ${data.student.enrollment}`;
    resultCard.querySelector('[data-result-score]').textContent = Number(data.score).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 2 });
    resultCard.querySelector('[data-result-correct]').textContent = data.correct;
    resultCard.querySelector('[data-result-wrong]').textContent = data.wrong;
    resultCard.querySelector('[data-result-blank]').textContent = data.blank;
    resultCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  scanNext.addEventListener('click', () => {
    if (input) input.value = '';
    ctx.clearRect(0,0,canvas.width,canvas.height);
    previewWrap.hidden = true;
    scanActions.hidden = true;
    review.hidden = true;
    resultCard.hidden = true;
    document.querySelector('.scanner-layout').hidden = false;
    stopCamera();
    captureChoices.hidden = false;
    liveCamera.hidden = true;
    manualStudent.value = '';
    clearStudent();
    detectedAnswers = [];
    globalConfidence = 0;
    qualityResolution.textContent = 'Resolução: —';
    qualityLight.textContent = 'Luz: —';
    qualityMarkers.textContent = 'Marcadores: aguardando';
    setStatus('Abrindo próximo scanner…');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    startCamera();
  });
})();
