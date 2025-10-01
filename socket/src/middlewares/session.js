const cookieSession = require('cookie-session');
const { config } = require('../config.js');

module.exports = cookieSession({
    name: 'session',
    httpOnly: true,
    keys: [config.sessionSecret],
    maxAge: 24 * 60 * 60 * 1000,
    secure: process.env.NODE_ENV === 'production',
});