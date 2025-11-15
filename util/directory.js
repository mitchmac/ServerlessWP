const fs = require('fs');
const execSync = require("child_process").execSync;

exports.setup = function() {
    if (!fs.existsSync('/tmp/wp')) {
        try {
            // Symlink entire WordPress directory for fast read access to core files
            fs.symlinkSync('/var/task/wp', '/tmp/wp');

            // Remove wp-content symlink and replace with real writable copy
            // This is needed for SQLite db.php and any plugin-generated files
            fs.unlinkSync('/tmp/wp/wp-content');
            execSync('cp -R /var/task/wp/wp-content /tmp/wp/');
        }
        catch (err) {
            console.log(err);
        }
    }
}