const path = require('path');

module.exports = {
    entry: {
        'rtc_client': './src/rtc_client.js'
    },
    output: {
        filename: '[name].js',
        path: path.resolve(__dirname, 'dist')
    },    
    module: {
        rules:[
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: "babel-loader"
                }
            },
        ]
    },
    mode: 'development',
    optimization: {
        minimize: false
    }
}