exports.validate = function(response) {
  let hasSqliteS3 = false;
  let hasSQL = false;
  let platform = 'AWS';
  let dashboardLink;

  if (process.env['SQLITE_S3_BUCKET']) {
    hasSqliteS3 = true;
  }

  if (process.env['DATABASE'] && process.env['USERNAME'] && process.env['PASSWORD'] && process.env['HOST']) {
    hasSQL = true;
  }

  if (process.env['SITE_NAME']) {
    platform = 'Netlify';
    dashboardLink = `https://app.netlify.com/sites/${process.env['SITE_NAME']}/settings/env`;
  }
  else if (process.env['VERCEL']) {
    platform = 'Vercel';
    dashboardLink = 'https://vercel.com/dashboard';
  }
  else {
    dashboardLink = 'https://console.aws.amazon.com/console/home';
  }

  if (!hasSQL && !hasSqliteS3) {
    let data = {};
    data.dashboardLink = dashboardLink;
    data.platform = platform;

    data.database = process.env['DATABASE'] ? '✔️' : '❌';
    data.username = process.env['USERNAME'] ? '✔️' : '❌';
    data.password = process.env['PASSWORD'] ? '✔️' : '❌';
    data.host = process.env['HOST'] ? '✔️' : '❌';
    data.sqliteBucket = process.env['SQLITE_S3_BUCKET'] ? '✔️' : '❌';
    data.sqliteKey = process.env['SQLITE_S3_API_KEY'] ? '✔️' : '❌';
    data.sqliteSecret = process.env['SQLITE_S3_API_SECRET'] ? '✔️' : '❌';
    data.sqliteRegion = process.env['SQLITE_S3_REGION'] ? '✔️' : '❌';
 
    return {
      statusCode: 200,
      headers: {
        'content-type': 'text/html; charset=utf-8'
      },
      body: loadTemplate(data)
    }
  }
}

function loadTemplate(data) {
return `<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <title>ServerlessWP</title>
    <style>
      ul li {
        list-style: none;
      }
    </style>
  </head>
  <body>
    <main class="container" style="max-width: 800px; margin: 50px auto 0">
      <p align="center" style="margin-bottom: 50px"><img src="https://serverlesswp.com/wp-content/serverlesswp.png"></p>
      <p>ServerlessWP is installed!</p>
      <p>Now we need to add a database for WordPress.</p>
      <p>One of the easiest ways to do that right now is with a free <a href="https://www.pingcap.com/tidb-cloud-serverless/" target="_blank">TiDB cloud database</a>.</p>
      <p>The following environment variables need to be populated in your project at ${data.platform} after creating a database.</p>
      <ul>
        <li>${data.database} DATABASE (the database's name)</li>
        <li>${data.username} USERNAME (the username to connect to the database)</li>
        <li>${data.password} PASSWORD (the password to connect to the database)</li>
        <li>${data.host} HOST (the hostname to connect to the database)</li>
      </ul>
      <p>Go to your project's <a href="${data.dashboardLink}" target="_blank">dashboard</a> at ${data.platform} to make sure these values for your database details are entered. <strong>Then remember to re-deploy your project!</strong></p>
      <p>Having trouble? Send a <a href="https://serverlesswp.com/chat" target="_blank">chat message</a>.</p>
      <br>
      <br>
      <article><details>
        <summary>SQLite + S3 database alternative</summary>
        <p>Want to try WordPress without a database server?</p>
        <p>A super low cost and low maintenance way to handle WordPress data is to combine SQLite as a database with S3 storage. ServerlessWP does all of the hard work to keep the data in-sync.<p>
        <p>It's great for blogs, portfolios, and documentation sites.</p>
        <p>Check out the project <a href="https://github.com/mitchmac/serverlesswp/?tab=readme-ov-file#sqlite--s3-database-option" target="_blank">readme</a> for more details.</p>
        <p>The environment variables to try this approach should be:</p>
        <ul>
          <li>${data.sqliteBucket} SQLITE_S3_BUCKET (bucket name you created)</li>
          <li>${data.sqliteKey} SQLITE_S3_API_KEY (key to access the bucket)</li>
          <li>${data.sqliteSecret} SQLITE_S3_API_SECRET (key to access the bucket)</li>
          <li>${data.sqliteRegion} SQLITE_S3_REGION (region where the bucket lives - create it near your serverless functions)</li>
        </ul>
      </details></article>
      <br>
      <br>
      <sub>This page is auto-generated as part of the <a href="https://serverlesswp.com">ServerlessWP</a> installation.</sub>
      </main>
  </body>
</html>`;
}