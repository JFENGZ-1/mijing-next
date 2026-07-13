require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageMember/components/remarks"],
    {
      1564: function (e, n, t) {
        "use strict";
        t.r(n);
        var r = t("6080"),
          o = t.n(r);
        for (var a in r)
          ["default"].indexOf(a) < 0 &&
            (function (e) {
              t.d(n, e, function () {
                return r[e];
              });
            })(a);
        n.default = o.a;
      },
      "5ad4": function (e, n, t) {
        "use strict";
        t.d(n, "b", function () {
          return o;
        }),
          t.d(n, "c", function () {
            return a;
          }),
          t.d(n, "a", function () {
            return r;
          });
        var r = {
            ffPopup: function () {
              return t
                .e("components/ff-popup/ff-popup")
                .then(t.bind(null, "c29b"));
            },
            uButton: function () {
              return t
                .e("uview-ui/components/u-button/u-button")
                .then(t.bind(null, "d5d3"));
            },
          },
          o = function () {
            this.$createElement;
            var e =
                (this._self._c,
                this.remarksText && this.remarksText.length > 0),
              n = e ? this.remarksText.length : null;
            this.$mp.data = Object.assign({}, { $root: { g0: e, g1: n } });
          },
          a = [];
      },
      6080: function (e, n, t) {
        "use strict";
        Object.defineProperty(n, "__esModule", { value: !0 }),
          (n.default = void 0);
        var r = {
          props: { personalTainerInfo: Object, remarksTexts: String },
          data: function () {
            return { show: !1, flag: !0, remarksText: "" };
          },
          watch: { show: function (e) {} },
          methods: {
            submit: function () {
              this.$emit("remarksSubmit", this.remarksText), (this.show = !1);
            },
            headleClean: function () {
              this.remarksText = "";
            },
            open: function (e) {
              (this.show = !0),
                (this.remarksText = this.personalTainerInfo
                  ? this.personalTainerInfo.userRemark
                  : this.remarksTexts);
            },
          },
          computed: {},
        };
        n.default = r;
      },
      "6aaa": function (e, n, t) {},
      b36b: function (e, n, t) {
        "use strict";
        t.r(n);
        var r = t("5ad4"),
          o = t("1564");
        for (var a in o)
          ["default"].indexOf(a) < 0 &&
            (function (e) {
              t.d(n, e, function () {
                return o[e];
              });
            })(a);
        t("f9c7");
        var s = t("828b"),
          u = Object(s.a)(
            o.default,
            r.b,
            r.c,
            !1,
            null,
            "7e896066",
            null,
            !1,
            r.a,
            void 0,
          );
        n.default = u.exports;
      },
      f9c7: function (e, n, t) {
        "use strict";
        var r = t("6aaa");
        t.n(r).a;
      },
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageMember/components/remarks-create-component",
    {
      "pageMember/components/remarks-create-component": function (e, n, t) {
        t("df3c").createComponent(t("b36b"));
      },
    },
    [["pageMember/components/remarks-create-component"]],
  ]);
