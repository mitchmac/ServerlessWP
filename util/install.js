exports.validate = function(response) {
  let hasSqliteS3 = false;
  let hasSQL = false;

  if (process.env['SQLITE_S3_BUCKET']) {
    hasSqliteS3 = true;
  }

  if (process.env['DATABASE'] && process.env['USERNAME'] && process.env['PASSWORD'] && process.env['HOST']) {
    hasSQL = true;
  }

  if (process.env['SITE_NAME']) {
    dashboardLink = `https://app.netlify.com/sites/${process.env['SITE_NAME']}/settings/env`;
  }
  else if (process.env['VERCEL']) {
    dashboardLink = 'https://vercel.com/dashboard';
  }
  else {
    dashboardLink = 'https://console.aws.amazon.com/console/home';
  }

  if (!hasSQL && !hasSqliteS3) {
    let message = "<p>It appears that the required environment variables for the WordPress database aren't setup.</p>"
      + "<p>If you don't have a database solution selected yet, there are a few options.</p>"
      + "<p>Head over to the <a href='https://github.com/mitchmac/ServerlessWP?tab=readme-ov-file#2-select-a-database-solution' target='_blank'>readme</a> to pick a data option and setup environment variables.</p>"
      + `<p>You'll need to populate the environment variables for your site in the Vercel or Netlify (<a href="${dashboardLink}" target="_blank">dashboard</a>).</p>`
      + '<p>Then <strong>remember to redeploy your site at Vercel or Netlify</strong> for the environment variables to be updated for the site.</p>';

    return {
      statusCode: 500,
      headers: {
        'content-type': 'text/html; charset=utf-8'
      },
      body: loadTemplate(message)
    }
  }
}

function loadTemplate(message){
    return `
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
        <title>ServerlessWP WordPress Starter</title>
      </head>
      <body>
        <main class="container" style="width: 800px; margin: 0 auto">
          <h1>You're almost there!</h1>
          ${message}
        </main>
      </body>
    </html>
    `;
}