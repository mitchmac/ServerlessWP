exports.name = 'Sandbox Widget';

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
<div id="sandbox-widget" style="display:none;position:fixed;bottom:20px;right:20px;background:#1a1a1a;color:#fff;padding:12px 16px;border-radius:8px;font-family:system-ui,-apple-system,sans-serif;font-size:14px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:999999;max-width:250px;">
  <button onclick="document.getElementById('sandbox-widget').style.display='none';localStorage.setItem('sandbox-dismissed','1')" style="position:absolute;top:6px;right:8px;background:none;border:none;color:#999;cursor:pointer;font-size:16px;line-height:1;padding:0;" aria-label="Close">&#215;</button>
  <div style="font-weight:600;margin-bottom:4px;text-decoration:underline;">Sandbox Database</div>
  <div style="color:#fff;">Sandbox mode - <a style="color:#6bb4e9;" href="https://serverlesswp.com/changelog/try-serverlesswp-sandbox-with-sqlite-on-vercel/" target="_blank">SQLite database file</a> will expire in a few hours.</div>
</div>
<script>if(!localStorage.getItem('sandbox-dismissed')){document.getElementById('sandbox-widget').style.display='block';}</script>`;

  if (response.body.includes('</body>')) {
    response.body = response.body.replace('</body>', widgetHtml + '</body>');
  }
}