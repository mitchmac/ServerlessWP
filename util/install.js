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

  // @TODO: update text
  if (!hasSQL && !hasSqliteS3) {
    let message = "<p>It appears that the required environment variables for the WordPress database aren't setup.</p>"
      + "<p>If you don't have a database solution selected yet, there are a few options.</p>"
      + "<p>WordPress usually runs with a MySQL or MariaDB database. That means hosting a database that runs 24/7.</p>"
      + "<p>ServerlessWP now supports SQLite combined with S3 as a truly serverless database alternative. Combining SQLite with the recent ability to conditionally write to S3-compatible object storage allows for a zero maintenance and low cost data layer for ServerlessWP.</p>"
      + "<p>Two trade-offs exist using SQLite and S3 for ServerlessWP: some plugins may not be completely compatible with SQLite and requests might fail if simultaneous requests change the database (within milliseconds of each other).</p>"
      + "<p>So you'll need to create an S3 bucket or a MySQL/MariaDB database.</p>"
      + `<p>Then you'll need to populate the environment variables for your site at Vercel or Netlify (<a href="${dashboardLink}">dashboard</a>)</p>`
      + "<p>The required variables for <strong>SQLite and S3</strong> are:</p>"
      + `<pre><code>
           SQLITE_S3_BUCKET
           SQLITE_S3_API_KEY
           SQLITE_S3_API_SECRET
           SQLITE_S3_REGION
           SQLITE_S3_ENDPOINT (optional: use with Cloudflare R2 for example)
           </code></pre>`
      + "<p>The required variables for <strong>MySQL or MariaDB</strong> are:</p>"
      + `<pre><code>
            DATABASE
            USERNAME
            PASSWORD
            HOST
           </code></pre>`
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