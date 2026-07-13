require("../../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageMember/components/navigation/headPhoto"],
    {
      "058b": function (t, e, n) {
        "use strict";
        (function (t) {
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var n = {
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
                var e = t.getMenuButtonBoundingClientRect();
                return (
                  e.height +
                  2 * (e.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              back: function () {
                if (this.isBack) {
                  var e = getCurrentPages(),
                    n = e[e.length - 2];
                  n && n.$vm.reGetList && n.$vm.reGetList(),
                    setTimeout(function () {
                      t.navigateBack();
                    }, 500);
                } else this.$emit("back");
              },
            },
          };
          e.default = n;
        }).call(this, n("df3c").default);
      },
      "0c64": function (t, e, n) {
        "use strict";
        n.r(e);
        var a = n("36c2"),
          o = n("64aa");
        for (var c in o)
          ["default"].indexOf(c) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return o[t];
              });
            })(c);
        n("cddc");
        var i = n("828b"),
          u = Object(i.a)(
            o.default,
            a.b,
            a.c,
            !1,
            null,
            "cb7df2e2",
            null,
            !1,
            a.a,
            void 0,
          );
        e.default = u.exports;
      },
      "36c2": function (t, e, n) {
        "use strict";
        n.d(e, "b", function () {
          return a;
        }),
          n.d(e, "c", function () {
            return o;
          }),
          n.d(e, "a", function () {});
        var a = function () {
            this.$createElement;
            var t =
              (this._self._c,
              this.customBack ? null : this.imgsrc("/static/imgs/back.png"));
            this.$mp.data = Object.assign({}, { $root: { m0: t } });
          },
          o = [];
      },
      "64aa": function (t, e, n) {
        "use strict";
        n.r(e);
        var a = n("058b"),
          o = n.n(a);
        for (var c in a)
          ["default"].indexOf(c) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return a[t];
              });
            })(c);
        e.default = o.a;
      },
      cddc: function (t, e, n) {
        "use strict";
        var a = n("f54d");
        n.n(a).a;
      },
      f54d: function (t, e, n) {},
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageMember/components/navigation/headPhoto-create-component",
    {
      "pageMember/components/navigation/headPhoto-create-component": function (
        t,
        e,
        n,
      ) {
        n("df3c").createComponent(n("0c64"));
      },
    },
    [["pageMember/components/navigation/headPhoto-create-component"]],
  ]);
