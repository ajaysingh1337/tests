const URL = require("node:url");
const debug = require("debug");
const { appName, config } = require("../config.js");

const logFunc = (r) => {
	if (process.env.NODE_ENV !== "production") {
		let { method, status, url, baseURL, config } = r;

		const endp = url || config?.url;
		const base = baseURL || config?.baseURL;
		let str = URL.parse(endp, base).href;

		if (method) str = `${method.toUpperCase()} ${str}`;
		if (status) str = `${status} ${str}`;

		debug(`${appName}:axios`)(str);
	}

	return r;
};

/**
 * Generic function for making requests to the Zoom API
 * @param {string | URL} endpoint - Zoom API Endpoint
 * @param {string} token - Access Token
 * @param {string} [method="post"] - Request method
 * @param {object} [data=null] - Request data
 */
function request(endpoint, token, method = "post", data = null) {
	let opt = {
		method,
		baseURL,
		url: endpoint,
		headers: {
			Authorization: `Bearer ${token}`,
		},
	};

	if (data) {
		opt.data =
			method.toLowerCase() === "post"
				? data
				: new URLSearchParams(data).toString();
	}

	if (method.toLowerCase() === "get") {
		opt.headers = {
			"Content-Type": "application/x-www-form-urlencoded",
		};
	}

	return axios(opt).then(({ data }) => Promise.resolve(data));
}

module.exports = { logFunc, request };
