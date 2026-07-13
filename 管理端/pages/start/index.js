(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/start/index"],
  {
    "200f": function (e, t, n) {
      "use strict";
      n.d(t, "b", function () {
        return r;
      }),
        n.d(t, "c", function () {
          return i;
        }),
        n.d(t, "a", function () {});
      var r = function () {
          this.$createElement;
          this._self._c;
        },
        i = [];
    },
    "2d0d": function (e, t, n) {
      "use strict";
      n.r(t);
      var r = n("200f"),
        i = n("e856");
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return i[e];
            });
          })(o);
      n("7b89");
      var s = n("828b"),
        a = Object(s.a)(
          i.default,
          r.b,
          r.c,
          !1,
          null,
          "24282023",
          null,
          !1,
          r.a,
          void 0,
        );
      t.default = a.exports;
    },
    "5dd1": function (e, t, n) {},
    "65db": function (e, t, n) {
      "use strict";
      (function (e) {
        var r = n("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var i = r(n("7ca3")),
          o = n("6b61");
        function s(e, t) {
          var n = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var r = Object.getOwnPropertySymbols(e);
            t &&
              (r = r.filter(function (t) {
                return Object.getOwnPropertyDescriptor(e, t).enumerable;
              })),
              n.push.apply(n, r);
          }
          return n;
        }
        function a(e) {
          for (var t = 1; t < arguments.length; t++) {
            var n = null != arguments[t] ? arguments[t] : {};
            t % 2
              ? s(Object(n), !0).forEach(function (t) {
                  (0, i.default)(e, t, n[t]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(n),
                  )
                : s(Object(n)).forEach(function (t) {
                    Object.defineProperty(
                      e,
                      t,
                      Object.getOwnPropertyDescriptor(n, t),
                    );
                  });
          }
          return e;
        }
        var c = {
          data: function () {
            return {
              showPrivacy: !1,
              errText: "",
              isFirstShow: !0,
              isUserInfo: !1,
            };
          },
          onShow: function (e) {
            this.isFirstShow ? (this.isFirstShow = !1) : this.handleAddress({});
          },
          onLoad: function (e) {
            this.handleAddress(e);
          },
          methods: {
            handleAddress: function (t) {
              var n = this,
                r = {},
                i = null,
                s = null,
                c = null,
                u = null;
              if ("{}" != JSON.stringify(t))
                if (t.scene) {
                  var f = decodeURIComponent(t.scene),
                    d = this.GetQueryString(f);
                  (i = d.siteId), (s = d.c), (c = d.sid), (u = d.go);
                } else (i = t.siteId), (s = t.c), (c = t.sid), (u = t.go);
              i && (r.siteid = i),
                u || (u = 0),
                4 == u && c && (r.sid = c),
                console.log(i, s, c, u);
              var l = e.getStorageSync("authorizationInfo");
              l && l.avatarUrl && l.nickname && l.userphone
                ? (this.isUserInfo = !0)
                : (this.isUserInfo = !1),
                this.$store
                  .dispatch("getStopInfo", r)
                  .then(function (t) {
                    if (1 == u) {
                      if (!s)
                        return (
                          e.showToast({ title: "arrangeId为空", icon: "none" }),
                          !1
                        );
                      n.$store.dispatch("getAppointmentsParam", { dataid: s }),
                        n.href({
                          url: "/pagesCourse/leagueClassDetails/index?isOpen=".concat(
                            !1,
                          ),
                        });
                    } else if (2 == u) {
                      if (!s)
                        return (
                          e.showToast({ title: "drainerid为空", icon: "none" }),
                          !1
                        );
                      n.$store.dispatch("getAppointmentsParam", {}),
                        n.href({
                          url: "/pagesCourse/personalTrainerDetails/index?drainerId=".concat(
                            s,
                          ),
                        });
                    } else
                      3 == u
                        ? (0, o.getSiteInfo)().then(function (e) {
                            if (200 == e.code) {
                              var t = e.data,
                                r = e.customServicer,
                                i = e.servicerNickName,
                                o = e.protocolURL,
                                s = a(
                                  a({}, t),
                                  {},
                                  {
                                    customServicer: r,
                                    servicerNickName: i,
                                    protocolURL: o,
                                  },
                                );
                              n.$store.dispatch("getStopServeInfo", s),
                                n.href({ url: "/pageServer/index" });
                            }
                          })
                        : e.reLaunch({ url: "/pages/home/home" });
                  })
                  .catch(function (e) {
                    n.errText = e.msg;
                  });
            },
            GetQueryString: function (e) {
              for (
                var t = new Object(), n = e.split("&"), r = 0;
                r < n.length;
                r++
              )
                t[n[r].split("=")[0]] = unescape(n[r].split("=")[1]);
              return t;
            },
          },
        };
        t.default = c;
      }).call(this, n("df3c").default);
    },
    "7b89": function (e, t, n) {
      "use strict";
      var r = n("5dd1");
      n.n(r).a;
    },
    e856: function (e, t, n) {
      "use strict";
      n.r(t);
      var r = n("65db"),
        i = n.n(r);
      for (var o in r)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return r[e];
            });
          })(o);
      t.default = i.a;
    },
    fb0f: function (e, t, n) {
      "use strict";
      (function (e, t) {
        var r = n("47a9");
        n("86d2"), r(n("3240"));
        var i = r(n("2d0d"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = n), t(i.default);
      }).call(this, n("3223").default, n("df3c").createPage);
    },
  },
  [["fb0f", "common/runtime", "common/vendor"]],
]);
