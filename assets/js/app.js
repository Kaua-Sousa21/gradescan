(() => {
  const sidebar = document.querySelector('[data-sidebar]');
  const backdrop = document.querySelector('[data-sidebar-backdrop]');
  const toggle = document.querySelector('[data-sidebar-toggle]');
  const closeSidebar = () => document.body.classList.remove('sidebar-open');
  toggle?.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
  backdrop?.addEventListener('click', closeSidebar);
  sidebar?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    if (window.innerWidth < 1100) closeSidebar();
  }));

  document.querySelectorAll('[data-password-toggle]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.querySelector(btn.dataset.passwordToggle);
      if (!input) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.textContent = input.type === 'password' ? 'Ver' : 'Ocultar';
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Confirmar esta ação?')) e.preventDefault();
    });
  });

  document.querySelectorAll('[data-toast]').forEach((toast, index) => {
    const remove = () => { toast.classList.add('toast-out'); setTimeout(() => toast.remove(), 220); };
    toast.querySelector('[data-toast-close]')?.addEventListener('click', remove);
    setTimeout(remove, 4500 + index * 350);
  });

  const observer = 'IntersectionObserver' in window ? new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('in-view'); observer.unobserve(entry.target); }
    });
  }, { threshold: 0.08 }) : null;
  document.querySelectorAll('.reveal, .stagger').forEach(el => observer ? observer.observe(el) : el.classList.add('in-view'));

  document.querySelectorAll('[data-count]').forEach(el => {
    const finalValue = Number(el.dataset.count || 0);
    if (!Number.isFinite(finalValue) || finalValue > 9999) return;
    let start = null;
    const animate = ts => {
      if (!start) start = ts;
      const p = Math.min(1, (ts - start) / 650);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = String(Math.round(finalValue * eased));
      if (p < 1) requestAnimationFrame(animate);
    };
    requestAnimationFrame(animate);
  });

  const examBuilder = document.querySelector('[data-exam-builder]');
  if (examBuilder) {
    const countSelect = document.getElementById('questionCount');
    const key = document.getElementById('answerKey');
    const classSelect = document.getElementById('examClass');
    const subjectSelect = document.getElementById('examSubject');
    const teacherSelect = document.getElementById('examTeacher');

    function renderKey() {
      const count = Number(countSelect.value || 20);
      key.innerHTML = '';
      for (let q = 1; q <= count; q++) {
        const box = document.createElement('div');
        box.className = 'answer-key-item';
        box.innerHTML = `<span class="key-number">${String(q).padStart(2,'0')}</span><div class="key-options">${['A','B','C','D','E'].map(l => `<label><input type="radio" name="answer_${q}" value="${l}" required><span>${l}</span></label>`).join('')}</div>`;
        key.appendChild(box);
      }
    }

    function filterClasses() {
      if (!teacherSelect) return;
      const teacherId = teacherSelect.value;
      let firstVisible = null;
      [...classSelect.options].forEach((option, index) => {
        if (index === 0) return;
        const show = option.dataset.teacher === teacherId;
        option.hidden = !show;
        option.disabled = !show;
        if (show && !firstVisible) firstVisible = option;
      });
      const selected = classSelect.selectedOptions[0];
      if (!selected || selected.disabled || selected.hidden) classSelect.value = firstVisible?.value || '';
    }

    function filterSubjects() {
      const classId = classSelect.value;
      const teacherId = teacherSelect?.value || '';
      let first = true;
      [...subjectSelect.options].forEach((option, index) => {
        if (index === 0 || !option.dataset.class) { option.hidden = true; option.disabled = true; return; }
        const showClass = option.dataset.class === classId;
        const showTeacher = !teacherSelect || option.dataset.teacher === teacherId;
        const show = showClass && showTeacher;
        option.hidden = !show;
        option.disabled = !show;
        if (show && first) { option.selected = true; first = false; }
      });
      if (first) subjectSelect.value = '';
    }

    function refreshAssignmentFilters() {
      filterClasses();
      filterSubjects();
    }

    countSelect.addEventListener('change', renderKey);
    classSelect.addEventListener('change', filterSubjects);
    teacherSelect?.addEventListener('change', refreshAssignmentFilters);
    renderKey();
    refreshAssignmentFilters();
  }
})();
