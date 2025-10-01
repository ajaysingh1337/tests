import React from "react";
import CallHandler from "./CallHandler";
import "font-awesome/css/font-awesome.min.css";
import * as common from "../../assets/js/helpers/common";
import bus from "../bus";
import { withTranslation } from "react-i18next";
import { AppContext } from "reactComponents/Contexts";
import RtcHelpers from "webrtc-client/src/rtc_helpers";
import "../../assets/css/call/wn_call.css";


class WnCall extends React.Component {
    static contextType = AppContext;

    constructor(props) {
        super(props);
        this.chat_id = window.app_data.id;
        this.room_id = window.room_id;
        this.is_video = false;
        this.incoming_call = window.incoming_call;
        this.current_user = window.current_user;
        this.call_handler_ref = React.createRef();
       
       
        this.state = {
            call_options: false,
            call: {app_data:window.app_data},
            host_ids: [],
            is_host: false,
            user_status: '',
            call_status: '',
            show_closing_screen: false
        };
        if (this.current_user?.role === 'teacher') {
            this.state.is_host = true;
            this.state.host_ids = [this.current_user.id];
        }
        console.log('this.state',this.state);

    }

    componentDidMount() {
        this.rtc_helpers = new RtcHelpers({ socket: this.context.ms_socket, user: this.current_user });
        this.attachBusListeners();
        this.prepareJoinCall();

        

        const channel = new BroadcastChannel('calling_broadcast');
        channel.onmessage = (event) => {
            if(event.data.leave_call) {
                this.leaveCall();

                setTimeout(() => {
                    window.close();
                }, 3000);
            }
        };        
    }

    componentWillUnmount() {
        // fix Warning: Can't perform a React state update on an unmounted component
        this.setState = (state, callback) => {
            return;
        };
    }

    attachBusListeners() {
        

        bus.on('ms_socket__connected', (socket) => {
            console.log('WnCall ms_socket__connected');
            this.rtc_helpers.socket = socket;
            this.prepareJoinCall();
        });

        bus.on("call__left", (data) => {
            console.log("call__left", data);

            this.onCallLeft(data);
        });

        bus.on("call__join_cancelled", () => {
            console.log("call__join_cancelled");

            this.resetThings();
        });

        bus.on('call__joined', ({ user_id }) => {
            this.onCallJoined({ user_id });
        });
    }

    async prepareJoinCall(redial) {
        console.log('prepareJoinCall', this.chat_id, this.context.ms_socket, this.context.ms_socket && this.context.ms_socket.id, this.context.ms_socket && this.context.ms_socket.connected);

        var has_video = common.getUrlParameter('hasVideo');
        if(has_video === "true" || has_video === true) {
            this.is_video = true;
        }

        if(!this.context.ms_socket || !this.context.ms_socket.id) {
            this.wait_call_id = this.chat_id;
            return;
        }

        this.wait_call_id = false;
        window.incoming_call = false;       
      

        var call_options = {
            audio: { muted: false, can_turn_on: true, can_turn_off: true },
            video: { muted: !this.is_video, can_turn_on: true, can_turn_off: true },
            enable_join_options: false
        };

        var call = { chatId: this.chat_id,app_data:window.app_data };
        var is_calling = false;

        if(!this.incoming_call) {
            is_calling = true;
        
        } else {
            call.id = this.incoming_call.callId;
            call.chat_id = this.incoming_call.chatId;
            call.room_id = this.incoming_call.roomId;

            if(redial) {
                is_calling = true;
            }
        }
        call.room_id = this.room_id;
        let hostIds=[];
        if (this.current_user?.role === 'teacher') {
           hostIds = [this.current_user.id];
        }
        this.setState({
            call: call,
            call_options: call_options,
            host_ids: hostIds
          
        }, () => {
            this.call_handler_ref.current.joinCall({ call: call });
        });
    }

    onCallLeft({ soft_leave, user_id }) {
        console.log('onCallLeft',soft_leave,user_id,window.call_helpers.getParticipantsIds().length);
        if (soft_leave) return;
        if(!user_id) return;

        if(user_id == this.current_user.id) {           
            this.showClosingScreen();
        
        } else{
            setTimeout(() => {
            if(window.call_helpers.getParticipantsIds().length == 1) {
                this.leaveCall();
                this.showClosingScreen();
             }
            }, 1000);
            
        } 
        
    }

    onCallJoined({ user_id }) {
        if(user_id && user_id != this.current_user.id) {
            this.setState({ call_status: 'connected' });
        }
    }

    resetThings() {
        this.setState({
            call: false,
            call_options: false,
            user_status: '',
            is_host: false,
            host_ids: []
        })
    }

    leaveCall() {
      
        window.leaveCall();
        this.setState({ call_status: 'left' });
        this.closeWindow();
    }

    showClosingScreen() {
        this.setState({ show_closing_screen: true, call_status: 'left' });
    }

    closeWindow() {
        window.close();
    }

    redial() {
        this.prepareJoinCall(true);
    }

   
    
     
    render() {
        const { t } = this.props;
        console.log('CallHandler pre',this.state);
        return (
            <div className={'call-popup-container'}>
                <>
                <CallHandler
                    call={this.state.call}
                    call_options={this.state.call_options}
                    is_host={this.state.is_host}
                    host_ids={this.state.host_ids}
                    user_status={ this.state.user_status }
                    ref={ this.call_handler_ref }
                    tflite={this.props.tflite}
                />
                </>
            </div>
        );
    }
}

export default withTranslation()(WnCall);