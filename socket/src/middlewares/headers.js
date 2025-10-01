const headers = {
	frameguard: {
		action: "sameorigin",
	},
	hsts: {
		maxAge: 31536000,
	},
	referrerPolicy: "same-origin",
	crossOriginEmbedderPolicy: false,
	contentSecurityPolicy: {
		directives: {
			"default-src": ["'self'"],
			styleSrc: ["'self'", "*", "'unsafe-inline'"],
			scriptSrc: ["'self'", "*", "'unsafe-inline'", "'unsafe-eval'"],
			imgSrc: [
				"'self'",
				"*",
				"'unsafe-inline'",
				"'unsafe-eval'",
				"blob:",
				"data:",
			],
			"connect-src": ["'self'", "*"],
			"base-uri": "'self'",
			"frame-ancestors": ["'self'", "*"],
			// "form-action": "self",
		},
	},
};

module.exports = headers;
