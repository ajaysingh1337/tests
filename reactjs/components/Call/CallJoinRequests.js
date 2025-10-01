import React from "react";
import axios from "axios";
import bus from "../bus";
import { toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import "../../assets/css/call/call_join_requests.css";
import "../../assets/css/call/call_participant.css";
import { withTranslation } from "react-i18next";
import i18next from "i18next";
import { AppContext } from "reactComponents/Contexts";

class CallJoinRequests extends React.Component {
  static contextType = AppContext;

  constructor(props) {
    super(props);

    this.join_requests_url = "";
    this.update_status_url = "";
    this.fetched_data = false;

    this.state = {
      join_requests_data: [],
      is_loading: false,
      accept_reject_all_underway: false,
      pending_requests_count: 0,
    };

    this.onJoinRequestUpdated = this.onJoinRequestUpdated.bind(this);
  }

  componentDidMount() {
    this.prepareThings();
    this.attachBusListeners();
    this.attachSocketListeners();
  }

  componentWillUnmount() {
    // fix Warning: Can't perform a React state update on an unmounted component
    this.setState = (state, callback) => {
      return;
    };

    this.removeSocketListeners();
  }

  prepareThings() {
    this.join_requests_url = this.props.call_options.urls.join_requests;
    this.update_status_url = this.props.call_options.urls.update_join_request;
  }

  attachBusListeners() {
    var self = this;

    bus.on("participants_section__opened", function () {
      self.fetchJoinRequests();
    });
  }

  attachSocketListeners() {
    console.log('joinrequests attachSocketListeners', this.context.socket);
    if(this.context.socket){
    this.context.socket?.on("call__join_request", (data) => {
          var new_request = data.join_request;
          var join_requests = this.state.join_requests_data;

          if (new_request.status != "waiting") {
            toast.info(i18next.t("New Join Request"), {
              position: "bottom-left",
              className: "call-toast",
              hideProgressBar: true
            });
          }

          join_requests.forEach((request, index) => {
            if (request.id == new_request.id) {
              join_requests.splice(index, 1);
              return;
            }
          });

          this.processJoinRequest(new_request);

          var updated_requests = [new_request, ...join_requests];

          this.setState({
            join_requests_data: updated_requests,
          });

          this.joinRequestsCountUpdated(updated_requests);
        });

        this.context.socket.on(
          "call__join_request_updated",
          this.onJoinRequestUpdated.bind(this)
        );
    }
    
  }

  removeSocketListeners() {
    this.context.socket.removeAllListeners("call__join_request");
    this.context.socket.off("call__join_request_updated", this.onJoinRequestUpdated);
  }

  onJoinRequestUpdated(data) {
    var request = data.join_request;
    var join_requests = this.state.join_requests_data;

    join_requests.forEach((join_request, index) => {
      if (join_request.id == request.id) {
        join_request.request_status = request.request_status;
        return;
      }
    });

    this.setState({
      join_requests_data: join_requests,
    });
  }

  fetchJoinRequests() {
    if (this.state.is_loading || this.fetched_data || !this.join_requests_url)
      return false;

    this.setState({ is_loading: true });

    axios
      .get(this.join_requests_url, {
        params: {
          id: this.props.call.id,
          relationships: ["user"],
        },
      })
      .then((resp) => {
        var data = resp.data.data;

        if (data.length) {
          this.processJoinRequests(data);

          this.setState({
            join_requests_data: data,
          });

          this.joinRequestsCountUpdated(data);
        }
        this.fetched_data = true;
        this.setState({ is_loading: false });
      });
  }

  updateStatus(request, status) {
    this.updateRequestState(request, "update_status_underway", true);

    axios.post(
      this.update_status_url, 
      {
        id: request.id,
        status: status,
        host_ids: this.props.host_ids,
      }
    ).then((resp) => {
      this.updateRequestState(request, "request_status", status);
      this.updateRequestState(request, "update_status_underway", false);
    });
  }

  updateRequestState(request, key, value) {
    var index = this.state.join_requests_data.findIndex(
      (x) => x.id === request.id
    );

    //updating key inside object in array of objects
    this.setState((prevState) => {
      const join_requests_data = [...prevState.join_requests_data];
      join_requests_data[index][key] = value;

      this.calculatePendingRequests(join_requests_data);
      this.joinRequestsCountUpdated(join_requests_data);

      return { join_requests_data: join_requests_data };
    });
  }

  joinRequestsCountUpdated(join_requests) {
    var pending_requests = this.calculatePendingRequests(join_requests);

    bus.dispatch("join_requests__count_updated", pending_requests.length);
  }

  calculatePendingRequests(join_requests) {
    const pending_requests = join_requests.filter((request) =>
        request.request_status == "pending"
    );
    
    this.setState({ pending_requests_count: pending_requests.length });

    return pending_requests;
  }

  acceptAllRequests() {
    this.state.join_requests_data.forEach((request) => {
      if (request.request_status != "pending" || request.status == "waiting")
        return;

      this.updateStatus(request, "accepted");
    });

    this.setState({ accept_reject_all_underway: true });

    setTimeout(() => {
      this.setState({ accept_reject_all_underway: false });
    }, 10000);
  }

  rejectAllRequests() {
    this.state.join_requests_data.forEach((request) => {
      if (request.request_status != "pending" || request.status == "waiting")
        return;

      this.updateStatus(request, "rejected");
    });

    this.setState({ accept_reject_all_underway: true });

    setTimeout(() => {
      this.setState({ accept_reject_all_underway: false });
    }, 10000);
  }

  processJoinRequests(join_requests) {
    join_requests.forEach(request => {
      this.processJoinRequest(request);
    })
  }

  processJoinRequest(join_request) {
    if(!join_request.guest_user_id) return;

    join_request.user = {
      first_name: join_request.guest_user_name,
      profile_picture_url: process.env.REACT_APP_DEFAULT_PIC
    }
  }

  render() {
    const { t, i18n } = this.props;
    return (
      <div className="join-requests-container">
        {this.state.pending_requests_count > 0 && (
          <div className="bulk-actions">
            <button
              onClick={() => this.acceptAllRequests()}
              disabled={
                this.state.accept_reject_all_underway ||
                !this.state.pending_requests_count
              }
            >
              <i class="fas fa-check mr-1"></i>
              {i18n.t("Accept all")}
            </button>

            <button
              onClick={() => this.rejectAllRequests()}
              disabled={
                this.state.accept_reject_all_underway ||
                !this.state.pending_requests_count
              }
            >
              <i class="fas fa-times mr-1"></i>
              {i18n.t("Reject all")}
            </button>
          </div>
        )}

        {!this.state.is_loading &&
          this.state.join_requests_data.map((request) => {
            return (
              <div className="participant" key={request.id}>
                <span className="left-side">
                  <img
                    className="profile-picture"
                    src={request.user.profile_picture_url}
                  />

                  <span className="full_name">{request.user.first_name}</span>
                </span>

                {request.request_status == "pending" ? (
                  <span className="right-side actions">
                    {request.status == "waiting" ? (
                      <button
                        className="button"
                        onClick={() =>
                          this.updateStatus(request, t("accepted"))
                        }
                        disabled={request.update_status_underway}
                      >
                        {t("move back to call")}
                      </button>
                    ) : (
                      <>
                        <button
                          onClick={() => this.updateStatus(request, "accepted")}
                          disabled={request.update_status_underway}
                        >
                          <i class="fas fa-check"></i>
                        </button>

                        <button
                          className="reject-btn"
                          onClick={() => this.updateStatus(request, "rejected")}
                          disabled={request.update_status_underway}
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </>
                    )}
                  </span>
                ) : (
                  <span className="right-side actions">
                    <i>{request.request_status}</i>
                  </span>
                )}

                <div className="clearfix"></div>
              </div>
            );
          })}

        {this.state.is_loading && (
          <div className="loader">
            <i className="fa fa-circle-notch fa-spin"></i>
          </div>
        )}

        {!this.state.join_requests_data.length && !this.state.is_loading && (
          <div className="no_data">{t("No request found")}</div>
        )}
      </div>
    );
  }
}
export default withTranslation()(CallJoinRequests);
