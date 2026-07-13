(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pages/start/index"],
  {
    "0cb3": function (e, n, t) {
      var i = t("b369");
      t.n(i).a;
    },
    "2bff": function (e, n, t) {
      (function (e, n) {
        var i = t("47a9");
        t("9785"), i(t("3240"));
        var a = i(t("9bec"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = t), n(a.default);
      }).call(this, t("3223").default, t("df3c").createPage);
    },
    "536e": function (e, n, t) {
      (function (e) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var t = {
          data: function () {
            return { startLogo: "", errText: "" };
          },
          onLoad: function (e) {
            this.handleAddress(e);
          },
          methods: {
            GetQueryString: function (e) {
              for (
                var n = new Object(), t = e.split("&"), i = 0;
                i < t.length;
                i++
              )
                n[t[i].split("=")[0]] = unescape(t[i].split("=")[1]);
              return n;
            },
            handleAddress: function (n) {
              var t = this,
                i = {},
                a = null,
                o = null,
                r = null,
                u = null;
              if ("{}" != JSON.stringify(n))
                if (n.scene) {
                  var c = decodeURIComponent(n.scene),
                    s = this.GetQueryString(c);
                  (a = s.siteId), (o = s.c), (r = s.go), (u = s.sid);
                } else (a = n.siteId), (o = n.c), (r = n.go), (u = n.sid);
              a && (i.siteid = a),
                u && (i.sid = u),
                console.log(
                  "启动页参数site---"
                    .concat(a, ",c---")
                    .concat(o, ",go---")
                    .concat(r, ",sid---")
                    .concat(u, "}"),
                ),
                this.$store
                  .dispatch("getLoginInfo", i)
                  .then(function (n) {
                    var i = n.sitelist.find(function (e) {
                      return 1 == e.isdefault;
                    });
                    (t.startLogo = i.startLogo), r || (r = 0);
                    var u = { go: r, siteId: a, c: o };
                    if ((e.setStorageSync("skipDate", u), a && n.isVisitor))
                      e.setStorage({
                        key: "siteId",
                        data: a,
                        success: function (n) {
                          setTimeout(function () {
                            e.reLaunch({
                              url: "/pages/authorization/info/index",
                            });
                          }, 1200);
                        },
                      });
                    else if (1 == r)
                      setTimeout(function () {
                        e.reLaunch({ url: "/pages/mine/index" });
                      }, 1200);
                    else if (2 == r) {
                      if (!o)
                        return (
                          e.showToast({
                            title: "未找到arrangeId",
                            icon: "none",
                            mask: !0,
                          }),
                          !1
                        );
                      t.$store.dispatch("getAppointmentsParam", {
                        dataid: o,
                        dataidType: 0,
                      }),
                        setTimeout(function () {
                          e.reLaunch({
                            url: "/pageCourse/clusterCourse/index?arrangeId=".concat(
                              o,
                            ),
                          });
                        }, 1200);
                    } else if (3 == r) {
                      if (!o)
                        return (
                          e.showToast({
                            title: "未找到drainerId",
                            icon: "none",
                            mask: !0,
                          }),
                          !1
                        );
                      t.$store.dispatch("getAppointmentsParam", {}),
                        setTimeout(function () {
                          e.reLaunch({
                            url: "/pageCourse/coachCourse/index?drainerId=".concat(
                              o,
                            ),
                          });
                        }, 1200);
                    } else
                      5 == r
                        ? setTimeout(function () {
                            e.reLaunch({
                              url: "/pages/appointmentCourse/index",
                            });
                          }, 1200)
                        : 6 == r
                          ? setTimeout(function () {
                              e.reLaunch({ url: "/pages/mine/index" });
                            }, 1200)
                          : 7 == r
                            ? setTimeout(function () {
                                e.reLaunch({
                                  url: "/pageHome/buyingCard/index",
                                });
                              }, 1200)
                            : setTimeout(function () {
                                e.reLaunch({ url: "/pages/index/index" });
                              }, 1200);
                  })
                  .catch(function (e) {
                    t.errText = e.msg;
                  });
            },
          },
        };
        n.default = t;
      }).call(this, t("df3c").default);
    },
    "6d27": function (e, n, t) {
      t.d(n, "b", function () {
        return i;
      }),
        t.d(n, "c", function () {
          return a;
        }),
        t.d(n, "a", function () {});
      var i = function () {
          this.$createElement;
          this._self._c;
        },
        a = [];
    },
    "9bec": function (e, n, t) {
      t.r(n);
      var i = t("6d27"),
        a = t("a128");
      for (var o in a)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return a[e];
            });
          })(o);
      t("a011"), t("0cb3");
      var r = t("828b"),
        u = Object(r.a)(
          a.default,
          i.b,
          i.c,
          !1,
          null,
          "731e8662",
          null,
          !1,
          i.a,
          void 0,
        );
      n.default = u.exports;
    },
    a011: function (e, n, t) {
      var i = t("b0ce");
      t.n(i).a;
    },
    a128: function (e, n, t) {
      t.r(n);
      var i = t("536e"),
        a = t.n(i);
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            t.d(n, e, function () {
              return i[e];
            });
          })(o);
      n.default = a.a;
    },
    b0ce: function (e, n, t) {},
    b369: function (e, n, t) {},
  },
  [["2bff", "common/runtime", "common/vendor"]],
]);
