(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageConfig/common/vendor"],
  {
    1557: function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.delHolidayInfo = function (t) {
          return i.default.post("".concat(a, "/b/vacation/delHolidayInfo"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isWrite: !0,
            },
          });
        }),
        (e.getHolidayOfOneStaff = function (t) {
          return i.default.post(
            "".concat(a, "/b/vacation/getHolidayOfOneStaff"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.getMainHolidayList = function (t) {
          return i.default.post(
            "".concat(a, "/b/vacation/getMainHolidayList"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.saveVacation = function (t) {
          return i.default.post("".concat(a, "/b/vacation/saveVacation"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    "7fc0": function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.getClientConfig = function (t) {
          return i.default.post("".concat(a, "/b/setting/getClientConfig"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isQuery: !0,
            },
          });
        }),
        (e.getsavefaceimage = function () {
          return i.default.post(
            "".concat(a, "/b/setting/getsavefaceimage"),
            "",
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.getwarmHint = function (t) {
          return i.default.post("".concat(a, "/b/setting/getwarmHint"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isQuery: !0,
            },
          });
        }),
        (e.saveClientConfig = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/saveClientConfig"),
            t,
            { custom: { contentType: "application/json", isWrite: !0 } },
          );
        }),
        (e.saveWarmHint = function (t) {
          return i.default.post("".concat(a, "/b/setting/saveWarmHint"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        }),
        (e.savefaceimage = function (t) {
          return i.default.post("".concat(a, "/b/setting/savefaceimage"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    "962b": function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.getHintManagerConfig = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/getHintManagerConfig"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.getHintSetting = function (t) {
          return i.default.post("".concat(a, "/b/setting/getHintSetting"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isQuery: !0,
            },
          });
        }),
        (e.saveHintManagerConfig = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/saveHintManagerConfig"),
            t,
            { custom: { contentType: "application/json", isWrite: !0 } },
          );
        }),
        (e.saveHintSetting = function (t) {
          return i.default.post("".concat(a, "/b/setting/saveHintSetting"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    9763: function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.deletes = function (t) {
          return i.default.post("".concat(a, "/b/stopbusiness/delete"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isWrite: !0,
            },
          });
        }),
        (e.findStopbusinessofSite = function (t) {
          return i.default.post(
            "".concat(a, "/b/stopbusiness/findStopbusinessofSite"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.save = function (t) {
          return i.default.post("".concat(a, "/b/stopbusiness/save"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    a994: function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.getuserFieldSetting = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/getuserFieldSetting"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.saveuserFieldSetting = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/saveuserFieldSetting"),
            t,
            { custom: { contentType: "application/json", isWrite: !0 } },
          );
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    b680: function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.getuserProtocolSetting = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/getuserProtocolSetting"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isQuery: !0,
              },
            },
          );
        }),
        (e.saveuserPtotocolSetting = function (t) {
          return i.default.post(
            "".concat(a, "/b/setting/saveuserPtotocolSetting"),
            t,
            {
              custom: {
                contentType: "application/x-www-form-urlencoded",
                isWrite: !0,
              },
            },
          );
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
    baeb: function (t, e, n) {
      "use strict";
      var o = n("47a9");
      Object.defineProperty(e, "__esModule", { value: !0 }),
        (e.deletes = function (t) {
          return i.default.post("".concat(a, "/b/notice/deleteNotice"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isWrite: !0,
            },
          });
        }),
        (e.getNoticeList = function (t) {
          return i.default.post("".concat(a, "/b/notice/getNoticeList"), t, {
            custom: {
              contentType: "application/x-www-form-urlencoded",
              isQuery: !0,
            },
          });
        }),
        (e.save = function (t) {
          return i.default.post("".concat(a, "/b/notice/save"), t, {
            custom: { contentType: "application/json", isWrite: !0 },
          });
        });
      var i = o(n("eda1")),
        a = o(n("7502")).default.baseUrl;
    },
  },
]);
