var env = process.env.NODE_ENV || 'development';
const url = require('node:url');
const Redis = require('ioredis');
// if(env === 'development')
require('dotenv').config();

const config = process.env;
const deps = [
	"SESSION_SECRET",
	"API_URL"
];

// Check that we have all our config dependencies
let hasMissing = !config;
for (const dep in deps) {
    const conf = deps[dep];
    const str = config[conf];

    if (!str || typeof str !== 'string') {
        console.error(`${conf} is required`);
        hasMissing = true;
    }
}

if (hasMissing) throw new Error('Missing required .env values...exiting');

const app = {
	sessionSecret: config.SESSION_SECRET,
	apiUrl: config.API_URL,
    redisHost: config.REDIS_HOST || '127.0.0.1',
    redisPort: config.REDIS_PORT || 6379,
    redisUsername: config.REDIS_USERNAME || '',
    redisPassword: config.REDIS_PASSWORD || '',
    redisDb: config.REDIS_DB || 0,
};

const redis = new Redis({
    host: app.redisHost,
    port: app.redisPort,
    username: app.redisUsername,
    password: app.redisPassword,
    db: app.redisDb,
});

// Zoom App Info
const appName = config.APP_NAME || 'Ogoul Tutor';
const companyName = config.COMPANY_NAME || config.APP_NAME || "Ogoul Tutor";

// HTTP
const port = config.PORT || '4000';

// require secrets are explicitly imported
module.exports = {
    config: app,
    redis,
    appName,
    companyName,
    port,
    env
};
