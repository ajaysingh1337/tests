import React from 'react';
import ReactDOM from 'react-dom/client';
import App from 'reactComponents/App.js'
const rootElement = document.getElementById('react-root');
if(rootElement){
    const roomId = rootElement.dataset.roomId;
    const appData = JSON.parse(rootElement.dataset.appData);
    ReactDOM.createRoot(document.getElementById('react-root')).render(<App roomId={roomId} appData={appData} />);
}

