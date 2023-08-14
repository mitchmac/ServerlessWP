exports.validate = function(response) {
    if (
        !process.env['FQDBDIR'] && 
        (!process.env['DATABASE'] || 
        !process.env['USERNAME'] ||
        !process.env['PASSWORD'] ||
        !process.env['HOST'])
    ) {

        if (process.env['SITE_NAME']) {
            dashboardLink = `https://app.netlify.com/sites/${process.env['SITE_NAME']}/settings/env`;
        }
        else if (process.env['VERCEL']) {
            dashboardLink = 'https://vercel.com/dashboard';
        }
        else {
          dashboardLink = 'https://console.aws.amazon.com/console/home';
        }

        let message = "<p>It appears that the required environment variables for the WordPress database aren't setup.</p>"
        + "<p>If you don't have a database created yet, head over to <a href='https://planetscale.com'>PlanetScale</a> to create one.</p>"
        + `<p>Then you'll need to populate the environment variables for your site at Vercel or Netlify (<a href="${dashboardLink}">dashboard</a>)`
        + '<p>The required variables are:</p>'
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