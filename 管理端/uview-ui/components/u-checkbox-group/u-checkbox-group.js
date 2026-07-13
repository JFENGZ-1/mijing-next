(global.webpackJsonp = global.webpackJsonp || []).push([
  ["uview-ui/components/u-checkbox-group/u-checkbox-group"],
  {
    "40a6": function (e, t, n) {
      "use strict";
      var u = n("47a9");
      Object.defineProperty(t, "__esModule", { value: !0 }),
        (t.default = void 0);
      var o = {
        name: "u-checkbox-group",
        mixins: [u(n("2f8f")).default],
        props: {
          max: { type: [Number, String], default: 999 },
          disabled: { type: Boolean, default: !1 },
          name: { type: [Boolean, String], default: "" },
          labelDisabled: { type: Boolean, default: !1 },
          shape: { type: String, default: "square" },
          activeColor: { type: String, default: "#22C788" },
          size: { type: [String, Number], default: 34 },
          width: { type: String, default: "auto" },
          wrap: { type: Boolean, default: !1 },
          iconSize: { type: [String, Number], default: 20 },
        },
        data: function () {
          return {};
        },
        created: function () {
          this.children = [];
        },
        methods: {
          emitEvent: function () {
            var e = this,
              t = [];
            this.children.map(function (e) {
              e.value && t.push(e.name);
            }),
              this.$emit("change", t),
              setTimeout(function () {
                e.dispatch("u-form-item", "on-form-change", t);
              }, 60);
          },
        },
      };
      t.default = o;
    },
    "87c9": function (e, t, n) {
      "use strict";
      n.d(t, "b", function () {
        return u;
      }),
        n.d(t, "c", function () {
          return o;
        }),
        n.d(t, "a", function () {});
      var u = function () {
          this.$createElement;
          this._self._c;
        },
        o = [];
    },
    "9ecc": function (e, t, n) {},
    a476: function (e, t, n) {
      "use strict";
      n.r(t);
      var u = n("40a6"),
        o = n.n(u);
      for (var c in u)
        ["default"].indexOf(c) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return u[e];
            });
          })(c);
      t.default = o.a;
    },
    b8ea: function (e, t, n) {
      "use strict";
      n.r(t);
      var u = n("87c9"),
        o = n("a476");
      for (var c in o)
        ["default"].indexOf(c) < 0 &&
          (function (e) {
            n.d(t, e, function () {
              return o[e];
            });
          })(c);
      n("c962");
      var a = n("828b"),
        i = Object(a.a)(
          o.default,
          u.b,
          u.c,
          !1,
          null,
          "e070dd70",
          null,
          !1,
          u.a,
          void 0,
        );
      t.default = i.exports;
    },
    c962: function (e, t, n) {
      "use strict";
      var u = n("9ecc");
      n.n(u).a;
    },
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "uview-ui/components/u-checkbox-group/u-checkbox-group-create-component",
    {
      "uview-ui/components/u-checkbox-group/u-checkbox-group-create-component":
        function (e, t, n) {
          n("df3c").createComponent(n("b8ea"));
        },
    },
    [
      [
        "uview-ui/components/u-checkbox-group/u-checkbox-group-create-component",
      ],
    ],
  ]);
