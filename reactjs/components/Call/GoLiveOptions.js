import React, { useState, useEffect } from "react";
import "../../assets/css/call/join_options.css";
import { useTranslation } from "react-i18next";
import axios from "axios";
function GoLiveOptions({ meeting_id }) {
  const { t, i18n } = useTranslation();
  // const [show_me, setShowMe] = React.useState(true);
  // const [is_video_on, setIsVideoOn] = React.useState(!video_muted);
  // const [is_audio_on, setIsAudioOn] = React.useState(!audio_muted);
  const [isVideoOn, setisVideoOn] = useState(true);
  const [mute, setMute] = useState(false);
  const [seconds, setSeconds] = useState(0);
  const [isActive, setIsActive] = useState(false);

  const [youtubeIngestionUrl, setYoutubeIngestionUrl] = useState("");
  const [youtubeStreamName, setYoutubeStreamName] = useState("");
  const [facebookStreamKey, setFacebookStreamKey] = useState("");
  const [twitchStreamKey, setTwitchStreamKey] = useState("");

  const [mediaStream, setMediaStream] = useState(null);
  const [userFacing, setuserFacing] = useState(false);

  const [streamId, setstreamId] = useState("");
  const [broadcastId, setbroadcastId] = useState("");
  const [streamOptions, setstreamOptions] = useState({
    facebook: 0,
    youtube: 0,
    custom_stream: 0,
  });
  var gapi = window.gapi;

  var CLIENT_ID = process.env.REACT_APP_GOOGLE_CLIENT_ID;
  var API_KEY = process.env.REACT_APP_GOOGLE_API_KEY_YOUTUBE;
  /*   var CLIENT_ID = "651267299035-kr122u1o10mj8lg4gr84v1gae37bhmu0.apps.googleusercontent.com";
  var  API_KEY = "AIzaSyAyyT_kKaWO3inLJTqfinTXnrhUDaUv1jQ"; */

  var DISCOVERY_DOCS = [
    "https://www.googleapis.com/discovery/v1/apis/youtube/v3/rest",
  ];
  var SCOPES = "https://www.googleapis.com/auth/youtube.force-ssl";

  useEffect(() => {
    var getUserData = JSON.parse(localStorage.getItem("user"));
    var token = getUserData.login_token;
    axios.defaults.headers.common = {
      Authorization: "Bearer asdf",
      token: token,
    };
    axios.defaults.headers.post["token"] = token;
    axios
      .get("/user-preferences/get-stream-options", {
        headers: {
          token,
        },
        data: "",
      })
      .then((response) => {
        console.log("response", response);
        if (response.data.meta.code == "200") {
          console.log("golive", response.data.data);
          setstreamOptions({
            facebook: response.data.data.is_facebook,
            youtube: response.data.data.is_youtube,
            custom_stream: response.data.data.is_custom_stream,
          });
        }
      })
      .catch((err) => console.log(err));
  }, []);

  console.log(streamOptions);

  // useEffect(() => {
  //   var data = "";
  //   var getUserData = JSON.parse(localStorage.getItem("user"));
  //   var token = getUserData.login_token;
  //   axios.defaults.headers.common = {
  //     Authorization: "Bearer asdf",
  //     token: token,
  //   };
  //   var config = {
  //     method: "get",
  //     maxBodyLength: Infinity,
  //     url: "https://mizdah.mizdah.com/api/user-preferences/get-stream-options",
  //     headers: {
  //       token: token,
  //     },
  //     data: data,
  //   };

  //   axios(config)
  //     .then(function (response) {
  //       console.log(JSON.stringify(response.data));
  //     })
  //     .catch(function (error) {
  //       console.log(error);
  //     });
  // }, []);

  const GoLiveWithYoutube = () => {
    console.log("GoLiveWithYoutube");
    // authenticate();
    gapi.load("client:auth2", () => {
      console.log("loaded client");

      gapi.client.init({
        apiKey: API_KEY,
        clientId: CLIENT_ID,
        discoveryDocs: DISCOVERY_DOCS,
        scope: SCOPES,
        plugin_name: "Mizdah",
      });

      gapi.client.load("youtube", "v3", () => console.log("youtube!"));

      gapi.auth2
        .getAuthInstance()
        .signIn()
        /* gapi.auth2.init({client_id: CLIENT_ID}) */
        .then(() => {
          // loadClient();
          // createBroadcast();
          createStream();
        });
    });
  };

  const GoLiveWithFacebook = () => {
    console.log("GoLiveWithFacebook");
  };

  const GoLiveWithCustom = () => {
    console.log("GoLiveWithYoutube");
  };

  const authenticate = () => {
    return gapi.auth2
      .getAuthInstance()
      .signIn({ scope: "https://www.googleapis.com/auth/youtube.force-ssl" })
      .then((res) => {
        console.log(res);
        loadClient();
      })
      .catch((err) => console.log(err));
  };

  const loadClient = () => {
    gapi.client.setApiKey(API_KEY);
    return gapi.client
      .load("https://www.googleapis.com/discovery/v1/apis/youtube/v3/rest")
      .then((res) => {
        console.log("GAPI client loaded for API");
        console.log(res);
        createStream();
      })
      .catch((err) => console.log("Error loading GAPI client for API", err));
  };

  //!!! createBroadcast IS CALLED SECOND. BROADCAST APPEARS ON YOUTUBE
  const createBroadcast = () => {
    return gapi.client.youtube.liveBroadcasts
      .insert({
        part: ["id,snippet,contentDetails,status"],
        resource: {
          snippet: {
            title: `New Video: ${new Date().toISOString()}`,
            scheduledStartTime: `${new Date().toISOString()}`,
            description:
              "A description of your video stream. This field is optional.",
          },
          contentDetails: {
            recordFromStart: true,
            // startWithSlate: true,
            enableAutoStart: false,
            monitorStream: {
              enableMonitorStream: false,
            },
          },
          status: {
            privacyStatus: "public",
            selfDeclaredMadeForKids: true,
          },
        },
      })
      .then((res) => {
        console.log("Response", res);
        console.log(res.result.id);
        setbroadcastId(res.result.id);
      })
      .catch((err) => {
        console.error("Execute error", err);
      });
  };

  //!!! CALL createStream AFTER createBroadcast. IN THE RESPONSE SET youtubeIngestionUrl AND youtubeStreamName
  const createStream = () => {
    return gapi.client.youtube.liveStreams
      .insert({
        part: ["snippet,cdn,contentDetails,status"],
        resource: {
          snippet: {
            title: "Your new video stream's name",
            description:
              "A description of your video stream. This field is optional.",
          },
          cdn: {
            frameRate: "variable",
            ingestionType: "rtmp",
            resolution: "variable",
            format: "",
          },
          contentDetails: {
            isReusable: true,
          },
        },
      })
      .then((res) => {
        console.log("Response", res);

        setstreamId(res.result.id);
        console.log("streamID" + res.result.id);

        setYoutubeIngestionUrl(res.result.cdn.ingestionInfo.ingestionAddress);
        console.log(res.result.cdn.ingestionInfo.ingestionAddress);

        setYoutubeStreamName(res.result.cdn.ingestionInfo.streamName);
        console.log(res.result.cdn.ingestionInfo.streamName);
      })
      .catch((err) => {
        console.log("Execute error", err);
      });
  };
  //  End youtube.com

  return (
    <div>
      {streamOptions.facebook == 1 ? (
        <button
          className="raise-hand-btn action-btn dropdown-only-btn dropdown-item"
          onClick={GoLiveWithFacebook}
        >
          <i className="fas fa-record-vinyl action-icon"></i>
          <div className="action-title">{t("Go Live with Facebook")}</div>
        </button>
      ) : (
        ""
      )}

      {streamOptions.youtube == 1 ? (
        <button
          className="raise-hand-btn action-btn dropdown-only-btn dropdown-item"
          onClick={GoLiveWithYoutube}
        >
          <i className="fas fa-record-vinyl action-icon"></i>
          <div className="action-title">{t("Go Live with Youtube")}</div>
        </button>
      ) : (
        ""
      )}

      {streamOptions.custom_stream == 1 ? (
        <button
          className="raise-hand-btn action-btn dropdown-only-btn dropdown-item"
          onClick={GoLiveWithCustom}
        >
          <i className="fas fa-record-vinyl action-icon"></i>
          <div className="action-title">{t("Go Live with Custom Stream")}</div>
        </button>
      ) : (
        ""
      )}
    </div>
  );
}

export default GoLiveOptions;
