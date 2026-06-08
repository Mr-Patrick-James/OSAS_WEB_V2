let notificationHideTimer = null;
let notificationRemoveTimer = null;

function showNotification(message, type = 'info', titleOrDuration = null, durationMaybe = null) {
  let title = null;
  let duration = 5000;

  if (typeof titleOrDuration === 'number') duration = titleOrDuration;
  if (typeof titleOrDuration === 'string') title = titleOrDuration;
  if (typeof durationMaybe === 'number') duration = durationMaybe;
  if (typeof durationMaybe === 'string' && title === null) title = durationMaybe;

  // Default titles per type
  const defaultTitles = {
    success: 'Success',
    error:   'Error',
    warning: 'Warning',
    info:    'Info'
  };
  const resolvedTitle = title || defaultTitles[type] || 'Notice';

  // Icon map (Boxicons - used in dashboard)
  const iconMap = {
    success: 'bx-check-circle',
    error:   'bx-error-circle',
    warning: 'bx-error',
    info:    'bx-info-circle'
  };
  const icon = iconMap[type] || 'bx-info-circle';

  // Inject styles once
  if (!document.querySelector('#notification-styles')) {
    const styles = document.createElement('style');
    styles.id = 'notification-styles';
    styles.textContent = `
      .notification-toast {
        position: fixed;
        top: 24px;
        right: 24px;
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.14), 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: stretch;
        opacity: 0;
        transform: translateX(120%) scale(0.95);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        z-index: 99999;
        min-width: 300px;
        max-width: 400px;
        overflow: hidden;
        font-family: 'Poppins', 'Inter', sans-serif;
        pointer-events: auto;
      }
      .notification-toast.show {
        opacity: 1;
        transform: translateX(0) scale(1);
      }
      .notification-toast.hide {
        opacity: 0;
        transform: translateX(120%) scale(0.95);
        transition: all 0.35s ease;
      }
      .notif-accent {
        width: 5px;
        flex-shrink: 0;
        border-radius: 14px 0 0 14px;
      }
      .notification-success .notif-accent { background: #22c55e; }
      .notification-error   .notif-accent { background: #ef4444; }
      .notification-warning .notif-accent { background: #f59e0b; }
      .notification-info    .notif-accent { background: #3b82f6; }

      .notif-inner {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px 14px 14px;
        flex: 1;
      }
      .notif-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1.2rem;
      }
      .notification-success .notif-icon-wrap { background: rgba(34,197,94,0.12);  color: #22c55e; }
      .notification-error   .notif-icon-wrap { background: rgba(239,68,68,0.12);  color: #ef4444; }
      .notification-warning .notif-icon-wrap { background: rgba(245,158,11,0.12); color: #f59e0b; }
      .notification-info    .notif-icon-wrap { background: rgba(59,130,246,0.12); color: #3b82f6; }

      .notif-body { flex: 1; min-width: 0; }
      .notification-title {
        display: block;
        font-size: 0.85rem;
        font-weight: 700;
        color: #111;
        margin-bottom: 2px;
        letter-spacing: -0.01em;
      }
      .notification-message {
        display: block;
        font-size: 0.78rem;
        color: #666;
        line-height: 1.4;
        word-break: break-word;
      }
      .notification-close {
        background: none;
        border: none;
        color: #bbb;
        cursor: pointer;
        font-size: 0.75rem;
        padding: 4px;
        transition: color 0.2s;
        align-self: flex-start;
        margin-top: 2px;
        flex-shrink: 0;
      }
      .notification-close:hover { color: #555; }

      .notif-progress {
        position: absolute;
        bottom: 0;
        left: 5px;
        right: 0;
        height: 3px;
        background: rgba(0,0,0,0.06);
        border-radius: 0 0 14px 0;
        overflow: hidden;
      }
      .notif-progress-bar {
        height: 100%;
        width: 100%;
        transform-origin: left;
        border-radius: inherit;
      }
      .notification-success .notif-progress-bar { background: #22c55e; }
      .notification-error   .notif-progress-bar { background: #ef4444; }
      .notification-warning .notif-progress-bar { background: #f59e0b; }
      .notification-info    .notif-progress-bar { background: #3b82f6; }
    `;
    document.head.appendChild(styles);
  }

  // Remove existing toast
  const existing = document.getElementById('notification-toast');
  if (existing) existing.remove();
  if (notificationHideTimer)   clearTimeout(notificationHideTimer);
  if (notificationRemoveTimer) clearTimeout(notificationRemoveTimer);

  // Build toast
  const notification = document.createElement('div');
  notification.id = 'notification-toast';
  notification.className = `notification-toast notification-${type}`;
  notification.innerHTML = `
    <div class="notif-accent"></div>
    <div class="notif-inner">
      <div class="notif-icon-wrap">
        <i class="bx ${icon}"></i>
      </div>
      <div class="notif-body">
        <span class="notification-title">${resolvedTitle}</span>
        <span class="notification-message">${String(message ?? '')}</span>
      </div>
      <button class="notification-close" type="button"><i class="bx bx-x"></i></button>
    </div>
    <div class="notif-progress">
      <div class="notif-progress-bar"></div>
    </div>
  `;

  document.body.appendChild(notification);

  // Animate in
  requestAnimationFrame(() => requestAnimationFrame(() => notification.classList.add('show')));

  // Shrink progress bar
  const bar = notification.querySelector('.notif-progress-bar');
  bar.style.transition = `transform ${duration}ms linear`;
  requestAnimationFrame(() => requestAnimationFrame(() => {
    bar.style.transform = 'scaleX(0)';
  }));

  // Close button
  notification.querySelector('.notification-close').addEventListener('click', () => {
    clearTimeout(notificationHideTimer);
    clearTimeout(notificationRemoveTimer);
    notification.classList.remove('show');
    notification.classList.add('hide');
    setTimeout(() => notification.remove(), 400);
  });

  // Auto dismiss
  notificationHideTimer = setTimeout(() => {
    if (!notification.parentElement) return;
    notification.classList.remove('show');
    notification.classList.add('hide');
    notificationRemoveTimer = setTimeout(() => notification.remove(), 400);
  }, duration);
}

function getNotificationIcon(type) {
  const icons = {
    success: 'bx-check-circle',
    error:   'bx-error-circle',
    warning: 'bx-error',
    info:    'bx-info-circle'
  };
  return icons[type] || icons.info;
}
