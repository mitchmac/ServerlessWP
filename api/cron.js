
exports.handler = async function (event, context, callback) {
    if (process.env['VERCEL']) {
        let cronUrl = 'https://' + process.env['VERCEL_URL'] + '/wp-cron.php';
        try {
            let response = await fetch(cronUrl);
            if (!response.ok) {
                console.error(`Failed to trigger cron: ${response.statusText}`);
            }
        } catch (error) {
            console.error(`Error triggering cron: ${error.message}`);
        }
    }
}