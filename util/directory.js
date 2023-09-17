const fs = require('fs');
const execSync = require("child_process").execSync;

exports.setup = function() {
    if (!fs.existsSync('/tmp/wp')) {
        fs.mkdirSync('/tmp/wp');
        try {
            execSync('cp -R /var/task/wp/* /tmp/wp/');
        }
        catch (err) {
            console.log(err);
        }
    }
}