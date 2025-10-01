export default {
    methods: {
      /**
       * Translate the given key.
       */
      __(key, replace = {}) {
        if (!key) {
          console.error('Translation key is undefined or empty');
          return key; // Return the key itself if it's invalid
        }
  
        var keys = key.split('.'); // Split only if key is valid
        var translation = this.$page.props.language;
  
        if (!translation) {
          console.error('Translation object is undefined');
          return key; // Return the key if translation object is missing
        }
  
        keys.forEach(function (keyTmp) {
          if (translation[keyTmp]) {
            if (typeof translation[keyTmp] === 'string') {
              var options = translation[keyTmp].split('|');
              translation = options[0];
            } else {
              translation = translation[keyTmp];
            }
          } else {
            translation = keyTmp; // Fallback to keyTmp if translation not found
          }
        });
  
        Object.keys(replace).forEach(function (key) {
          translation = translation.replace(':' + key, replace[key]);
        });
  
        return translation;
      },
  
      /**
       * Translate the given key with basic pluralization.
       */
      __n(key, replace = {}) {
        if (!key) {
          console.error('Translation key is undefined or empty');
          return key; // Return the key if it's invalid
        }
  
        var keys = key.split('.'); // Split only if key is valid
        var translation = this.$page.props.language;
  
        if (!translation) {
          console.error('Translation object is undefined');
          return key; // Return the key if translation object is missing
        }
  
        keys.forEach(function (keyTmp) {
          if (translation[keyTmp]) {
            if (typeof translation[keyTmp] === 'string') {
              var options = translation[keyTmp].split('|');
              translation = options[1] ?? options[0];
            } else {
              translation = translation[keyTmp];
            }
          } else {
            translation = keyTmp; // Fallback to keyTmp if translation not found
          }
        });
  
        Object.keys(replace).forEach(function (key) {
          translation = translation.replace(':' + key, replace[key]);
        });
  
        return translation;
      },
    },
  };
  