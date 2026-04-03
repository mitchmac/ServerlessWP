exports.name = 'Goldilock Widget';

exports.register = async function (bucket, secret) {
  const response = await fetch('https://data.serverlesswp.com/auto-register', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ bucket, secret }),
  });

  if (!response.ok) {
    const errorText = await response.text();
    throw new Error(`Auto registration failed: ${errorText}`);
  }
}

exports.postRequest = async function(event, response) {
  if (!response || !response.body) {
    return;
  }

  const contentType = response.headers?.['content-type'] || response.headers?.['Content-Type'] || '';
  if (!contentType.includes('text/html')) {
    return;
  }

  const widgetHtml = `
<div id="goldilock-widget" style="position:fixed;bottom:20px;right:20px;background:#1a1a1a;color:#fff;padding:12px 16px;border-radius:8px;font-family:system-ui,-apple-system,sans-serif;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:999999;max-width:250px;">
  <div style="font-weight:600;margin-bottom:4px;">Sandbox Database</div>
  <div style="color:#fbbf24;">Sandbox mode - database file will expire in a few hours.</div>
</div>`;

  if (response.body.includes('</body>')) {
    response.body = response.body.replace('</body>', widgetHtml + '</body>');
  }
}