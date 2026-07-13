(global.webpackJsonp = global.webpackJsonp || []).push([
  ["components/expiredAlert/expiredAlert"],
  {
    "0229": function (e, t, r) {
      "use strict";
      r.d(t, "b", function () {
        return i;
      }),
        r.d(t, "c", function () {
          return o;
        }),
        r.d(t, "a", function () {
          return n;
        });
      var n = {
          uPopup: function () {
            return r
              .e("uview-ui/components/u-popup/u-popup")
              .then(r.bind(null, "40dc"));
          },
        },
        i = function () {
          this.$createElement;
          var e =
              (this._self._c, this.imgsrc("/static/imgs/home_expires2.png")),
            t = this.imgsrc("/static/imgs/home_expires1.png");
          this.$mp.data = Object.assign({}, { $root: { m0: e, m1: t } });
        },
        o = [];
    },
    "0299": function (e, t, r) {},
    "6c26": function (e, t, r) {
      "use strict";
      r.r(t);
      var n = r("e8f2"),
        i = r.n(n);
      for (var o in n)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            r.d(t, e, function () {
              return n[e];
            });
          })(o);
      t.default = i.a;
    },
    e7a4: function (e, t, r) {
      "use strict";
      var n = r("0299");
      r.n(n).a;
    },
    e8f2: function (e, t, r) {
      "use strict";
      (function (e) {
        var n = r("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var i = n(r("7ca3")),
          o = r("6b61"),
          c = r("073c");
        function a(e, t) {
          var r = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var n = Object.getOwnPropertySymbols(e);
            t &&
              (n = n.filter(function (t) {
                return Object.getOwnPropertyDescriptor(e, t).enumerable;
              })),
              r.push.apply(r, n);
          }
          return r;
        }
        function s(e) {
          for (var t = 1; t < arguments.length; t++) {
            var r = null != arguments[t] ? arguments[t] : {};
            t % 2
              ? a(Object(r), !0).forEach(function (t) {
                  (0, i.default)(e, t, r[t]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(r),
                  )
                : a(Object(r)).forEach(function (t) {
                    Object.defineProperty(
                      e,
                      t,
                      Object.getOwnPropertyDescriptor(r, t),
                    );
                  });
          }
          return e;
        }
        var u = {
          name: "expiredAlert",
          props: { page: { type: String, default: "" } },
          data: function () {
            return { show: !1, daymunTitle: "", maxDay: 5, lastClickTime: 0 };
          },
          computed: {
            hasSoftwareExpire: function () {
              return this.$store.getters.getSoftwareExpire;
            },
          },
          watch: {
            "hasSoftwareExpire.daynum": function (e) {
              this.checkExpireStatus();
            },
          },
          methods: {
            handleDoubleClick: function () {
              0 === this.lastClickTime
                ? (this.lastClickTime = Date.now())
                : Date.now() - this.lastClickTime < 300
                  ? ((this.show = !1), (this.lastClickTime = 0))
                  : (this.lastClickTime = Date.now());
            },
            cancelbtn: function () {
              var t = ""
                .concat((0, c.getCurrentDay)(), "_")
                .concat(this.getTimePeriod());
              e.setStorageSync(t, "closed"), (this.show = !1);
            },
            getTimePeriod: function () {
              var e = new Date();
              return e.getHours() < 12
                ? "morning"
                : e.getHours() < 18
                  ? "afternoon"
                  : "evening";
            },
            checkIfClosed: function () {
              var t = ""
                .concat((0, c.getCurrentDay)(), "_")
                .concat(this.getTimePeriod());
              return "closed" === e.getStorageSync(t);
            },
            renew: function () {
              var t = this;
              e.showLoading({ title: "加载中", mask: !0 }),
                (0, o.getSiteInfo)().then(function (r) {
                  if ((e.hideLoading(), 200 == r.code)) {
                    var n = r.data,
                      i = r.customServicer,
                      o = r.servicerNickName,
                      c = r.protocolURL,
                      a = s(
                        s({}, n),
                        {},
                        {
                          customServicer: i,
                          servicerNickName: o,
                          protocolURL: c,
                        },
                      );
                    t.$store.dispatch("getStopServeInfo", a),
                      t.href({ url: "/pageServer/order" });
                  } else e.showToast({ title: r.msg });
                });
            },
            setExpireMessage: function () {
              var e = this.hasSoftwareExpire.daynum;
              (this.show =
                ("home" === this.page && e <= this.maxDay) ||
                ("other" === this.page && e <= 0)),
                e <= 0
                  ? (this.daymunTitle = "亲，软件已经到期了哦")
                  : e <= this.maxDay &&
                    (this.daymunTitle = "亲，软件快到期了哦");
            },
            checkExpireStatus: function () {
              this.hasSoftwareExpire && this.page && !this.checkIfClosed()
                ? this.setExpireMessage()
                : (this.show = !1);
            },
          },
          mounted: function () {
            this.checkExpireStatus();
          },
        };
        t.default = u;
      }).call(this, r("df3c").default);
    },
    f411: function (e, t, r) {
      "use strict";
      r.r(t);
      var n = r("0229"),
        i = r("6c26");
      for (var o in i)
        ["default"].indexOf(o) < 0 &&
          (function (e) {
            r.d(t, e, function () {
              return i[e];
            });
          })(o);
      r("e7a4");
      var c = r("828b"),
        a = Object(c.a)(
          i.default,
          n.b,
          n.c,
          !1,
          null,
          "038a11c8",
          null,
          !1,
          n.a,
          void 0,
        );
      t.default = a.exports;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "components/expiredAlert/expiredAlert-create-component",
    {
      "components/expiredAlert/expiredAlert-create-component": function (
        e,
        t,
        r,
      ) {
        r("df3c").createComponent(r("f411"));
      },
    },
    [["components/expiredAlert/expiredAlert-create-component"]],
  ]);
