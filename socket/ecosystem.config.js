require("dotenv").config();
const { displayName } = require("../package.json");

module.exports = {
	apps: [
		{
			name: displayName,
			cwd: process.cwd(),
			script: "npm",
			args: "start",
			watch: true,
			env: {
				port: process.env.PORT || 4000,
				NODE_ENV: process.env.NODE_ENV || "development"
			},
		},
	],
};
