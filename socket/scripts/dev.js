const concurrently = require('concurrently');
const { displayName } = require("../package.json");
// Configure our server environment variables for darwin/linux and win32
let command = `nodemon src/index.js`;

if (process.platform === 'win32')
    command = `set "DEBUG=${displayName}*" & ${command}`;
else command = `DEBUG="${displayName}*" ${command}`;

const { result } = concurrently([
    {
        command,
        name: 'dev-server',
        prefixColor: 'inverse.cyan',
    },
]);

result.catch((e) => console.error(e));
