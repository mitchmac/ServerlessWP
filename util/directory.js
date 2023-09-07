const fs = require('fs');
const execSync = require("child_process").execSync;

exports.setup = function() {
    if (!fs.existsSync('/tmp/wp')) {
        fs.mkdirSync('/tmp/wp');
        try {
            execSync('mv /var/task/wp /tmp/');
        }
        catch (err) {
            console.log(err);
        }
    }
}
