(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageHome/buyingCard/buySuccess"],
  {
    1804: function (n, t, e) {
      var u = e("2074");
      e.n(u).a;
    },
    2074: function (n, t, e) {},
    "36a9": function (n, t, e) {
      e.r(t);
      var u = e("a794"),
        a = e("f9d3");
      for (var o in a)
        ["default"].indexOf(o) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return a[n];
            });
          })(o);
      e("1804");
      var i = e("828b"),
        r = Object(i.a)(
          a.default,
          u.b,
          u.c,
          !1,
          null,
          "a70755f2",
          null,
          !1,
          u.a,
          void 0,
        );
      t.default = r.exports;
    },
    "6a0b": function (n, t, e) {
      (function (n, t) {
        var u = e("47a9");
        e("9785"), u(e("3240"));
        var a = u(e("36a9"));
        (n.__webpack_require_UNI_MP_PLUGIN__ = e), t(a.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    a502: function (n, t, e) {
      (function (n) {
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var e = {
          data: function () {
            return {
              confirBtnStyle: { width: "458rpx", height: "83rpx" },
              goHomeBtnStyle: {
                width: "458rpx",
                height: "83rpx",
                marginTop: "38rpx",
                backgroundColor: "#fff",
                color: "#181818",
              },
            };
          },
          methods: {
            goMine: function () {
              n.switchTab({ url: "/pages/mine/index" });
            },
            goHome: function () {
              n.switchTab({ url: "/pages/index/index" });
            },
          },
        };
        t.default = e;
      }).call(this, e("df3c").default);
    },
    a794: function (n, t, e) {
      e.d(t, "b", function () {
        return a;
      }),
        e.d(t, "c", function () {
          return o;
        }),
        e.d(t, "a", function () {
          return u;
        });
      var u = {
          uButton: function () {
            return e
              .e("node-modules/uview-ui/components/u-button/u-button")
              .then(e.bind(null, "be1a"));
          },
        },
        a = function () {
          this.$createElement;
          var n = (this._self._c, this.imgsrc("/static/imgs/success.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: n } });
        },
        o = [];
    },
    f9d3: function (n, t, e) {
      e.r(t);
      var u = e("a502"),
        a = e.n(u);
      for (var o in u)
        ["default"].indexOf(o) < 0 &&
          (function (n) {
            e.d(t, n, function () {
              return u[n];
            });
          })(o);
      t.default = a.a;
    },
  },
  [["6a0b", "common/runtime", "common/vendor"]],
]);
