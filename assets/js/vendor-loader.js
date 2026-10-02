(() => {
  const loaded = new Map();

  function loadFrom(sources, test) {
    if (test()) return Promise.resolve();
    const key = sources.join('|');
    if (loaded.has(key)) return loaded.get(key);

    const promise = new Promise((resolve, reject) => {
      let index = 0;
      const tryNext = () => {
        if (test()) { resolve(); return; }
        if (index >= sources.length) { reject(new Error('Não foi possível carregar uma biblioteca necessária.')); return; }
        const src = sources[index++];
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => {
          if (test()) resolve();
          else setTimeout(() => test() ? resolve() : tryNext(), 250);
        };
        script.onerror = () => tryNext();
        document.head.appendChild(script);
      };
      tryNext();
    });
    loaded.set(key, promise);
    return promise;
  }

  async function ensureJsQR() {
    await loadFrom([
      'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js',
      'https://unpkg.com/jsqr@1.4.0/dist/jsQR.js'
    ], () => typeof window.jsQR === 'function');
  }

  async function ensureOpenCV() {
    await loadFrom([
      'https://docs.opencv.org/4.13.0/opencv.js',
      'https://docs.opencv.org/4.x/opencv.js'
    ], () => Boolean(window.cv));
    if (window.cv instanceof Promise) {
      window.cv = await window.cv;
    }
    if (window.cv && typeof window.cv.Mat === 'function') return;
    await new Promise((resolve, reject) => {
      let tries = 0;
      const timer = setInterval(async () => {
        tries += 1;
        if (window.cv instanceof Promise) window.cv = await window.cv;
        if (window.cv && typeof window.cv.Mat === 'function') {
          clearInterval(timer); resolve();
        } else if (tries > 120) {
          clearInterval(timer); reject(new Error('OpenCV não ficou pronto a tempo.'));
        }
      }, 250);
    });
  }

  async function ensureQRCode() {
    await loadFrom([
      'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js',
      'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js'
    ], () => typeof window.QRCode === 'function');
  }

  window.GradeScanVendors = { ensureJsQR, ensureOpenCV, ensureQRCode };
})();
