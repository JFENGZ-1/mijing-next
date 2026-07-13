(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageMine/components/navigation/headPhoto"],
  {
    6600: function (t, n, e) {
      e.r(n);
      var a = e("6a70"),
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
    "6a70": function (t, n, e) {
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
            headUrl: { type: String, default: "" },
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
    "7abc": function (t, n, e) {
      var a = e("dd41");
      e.n(a).a;
    },
    b5b8: function (t, n, e) {
      e.r(n);
      var a = e("e49d"),
        o = e("6600");
      for (var i in o)
        ["default"].indexOf(i) < 0 &&
          (function (t) {
            e.d(n, t, function () {
              return o[t];
            });
          })(i);
      e("7abc");
      var c = e("828b"),
        u = Object(c.a)(
          o.default,
          a.b,
          a.c,
          !1,
          null,
          "8d6d4f3e",
          null,
          !1,
          a.a,
          void 0,
        );
      n.default = u.exports;
    },
    dd41: function (t, n, e) {},
    e49d: function (t, n, e) {
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
    "pageMine/components/navigation/headPhoto-create-component",
    {
      "pageMine/components/navigation/headPhoto-create-component": function (
        t,
        n,
        e,
      ) {
        e("df3c").createComponent(e("b5b8"));
      },
    },
    [["pageMine/components/navigation/headPhoto-create-component"]],
  ]);
