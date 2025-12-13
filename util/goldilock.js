
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