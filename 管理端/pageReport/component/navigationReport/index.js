(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/component/navigationReport/index"],
  {
    "2a73": function (t, n, e) {
      "use strict";
      var a = e("d13f");
      e.n(a).a;
    },
    "777b": function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("8d0b"),
        o = e.n(a);
      for (var i in a)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return a[t];
            });
          })(i);
      n.default = o.a;
    },
    "8d0b": function (t, n, e) {
      "use strict";
      (function (t) {
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var e = {
          name: "index",
          props: {
            text: { type: String, default: "" },
            background: { type: String, default: "#FBD128" },
            isBack: { type: Boolean, default: !0 },
            customBack: { type: Boolean, default: !1 },
          },
          computed: {
            StatusBar: function () {
              return this.$store.state.systemInfo.statusBarHeight;
            },
            CustomBar: function () {
              var n = t.getMenuButtonBoundingClientRect();
              return (
                n.height +
                2 * (n.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                2
              );
            },
          },
          methods: {
            back: function () {
              this.isBack ? t.navigateBack() : this.$emit("back");
            },
          },
        };
        n.default = e;
      }).call(this, e("df3c").default);
    },
    b971: function (t, n, e) {
      "use strict";
      e.r(n);
      var a = e("d463"),
        o = e("777b");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(i);
      e("2a73");
      var c = e("828b"),
        u = Object(c.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "0c701e88",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = u.exports;
    },
    d13f: function (t, n, e) {},
    d463: function (t, n, e) {
      "use strict";
      e.d(n, "b", function () {
        return a;
      }),
        e.d(n, "c", function () {
          return o;
        }),
        e.d(n, "a", function () {});
      var a = function () {
          this.$createElement;
          var t =
            (this._self._c,
            this.customBack ? null : this.imgsrc("/static/imgs/back.png"));
          this.$mp.data = Object.assign({}, { $root: { m0: t } });
        },
        o = [];
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageReport/component/navigationReport/index-create-component",
    {
      "pageReport/component/navigationReport/index-create-component": function (
        t,
        n,
        e,
      ) {
        e("df3c").createComponent(e("b971"));
      },
    },
    [["pageReport/component/navigationReport/index-create-component"]],
  ]);
