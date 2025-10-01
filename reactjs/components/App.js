import React, { useEffect, useState } from "react";
import io from "socket.io-client";
import { Provider } from "react-redux";
import store from "../redux/store.js"; 
import { AppContext } from "./Contexts.js";
import { ToastContainer } from "react-toastify";

import bus from "./bus.js";
import "bootstrap/dist/js/bootstrap.bundle.min";
import '../assets/js/i18n.js';
import WnCall from "./Call/WnCall.js";


function App(roomId,appData) {
    
    const [ms_socket, setMsSocket] = useState(false);
    const [socket, setSocket] = useState(false);
   
    console.log('window room_id,app_dat',roomId,appData,window.app_data);
    useEffect(() => {       

        var ms_socket = io(import.meta.env.VITE_CALL_SERVER_URL, { 
            query: { user_id: window.current_user.id },
            transports: ["websocket"] 
        });
       
        window.ms_socket = ms_socket;

       

        ms_socket.on('connect', function() {
            console.log('ms_socket connectedd');
            bus.dispatch('ms_socket__connected', ms_socket);
        });
        
       
        setMsSocket(ms_socket);
    }, []);
    

    return (
        <Provider store={store}>
            <AppContext.Provider value={{ socket, ms_socket }}>
                <ToastContainer />
                <WnCall />
            </AppContext.Provider>
        </Provider>
    )
}

export default App;