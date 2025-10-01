module.exports = async (req, res, next) => {
	if (
		req.useragent.isMobile &&
		!req.useragent.isBot &&
		req.headers["x-requested-with"] === "com.lifestream.ai"
	) {
		next();
	} else {
		return res.status(401).json({
			status: "failed",
			error: "FORBIDDEN",
			msg: "Authentication failed",
		});
	}
};
