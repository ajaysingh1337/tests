
const isJson = (str) => {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

const generateCode = (length = 6, prefix = null) => {
	var result = prefix ? prefix + "_" : "";
	var characters =
		"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
	for (var i = 0; i < length; i++) {
		result += characters.charAt(
			Math.floor(Math.random() * characters.length)
		);
	}
	return result;
};
const generateRandomPassword = (length = 8)=> {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789$#@';
    let password = '';
    for (let i = 0; i < length; i++) {
        const randomIndex = Math.floor(Math.random() * chars.length);
        password += chars[randomIndex];
    }
    return password;
}
const convertShorthandNumber = (number) => {
	if (number >= 1e9) {
		return (number / 1e9).toFixed(2) + "B";
	} else if (number >= 1e6) {
		return (number / 1e6).toFixed(2) + "M";
	} else if (number >= 1e3) {
		return (number / 1e3).toFixed(2) + "K";
	}
	return number;
}

const getFileType = (file) => {
	const mimeTypes = {
		image: /^image\/.*$/,
		audio: /^audio\/.*$/,
		video: /^video\/.*$/,
		document:
			/^(application\/pdf|application\/msword|application\/vnd.openxmlformats-officedocument.wordprocessingml.document|text\/plain|application\/json|application\/xml)$/,
		archive:
			/^(application\/zip|application\/x-tar|application\/x-gzip|application\/x-bzip2)$/,
	};

	for (const type in mimeTypes) {
		if (mimeTypes[type].test(file.mimetype)) {
			return type;
		}
	}

	return "file";
};

module.exports = { isJson, generateCode, convertShorthandNumber, getFileType, generateRandomPassword };