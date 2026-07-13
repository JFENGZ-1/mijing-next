require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/embershipAgreement/editAgreement"],
    {
      "75da": function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("ca11"),
          c = n("98d1");
        for (var i in c)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return c[t];
              });
            })(i);
        n("daa1");
        var a = n("828b"),
          r = Object(a.a)(
            c.default,
            o.b,
            o.c,
            !1,
            null,
            "185fb87b",
            null,
            !1,
            o.a,
            void 0,
          );
        e.default = r.exports;
      },
      "98d1": function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("d855"),
          c = n.n(o);
        for (var i in o)
          ["default"].indexOf(i) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return o[t];
              });
            })(i);
        e.default = c.a;
      },
      ca11: function (t, e, n) {
        "use strict";
        n.d(e, "b", function () {
          return c;
        }),
          n.d(e, "c", function () {
            return i;
          }),
          n.d(e, "a", function () {
            return o;
          });
        var o = {
            ffBottomLogo: function () {
              return n
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(n.bind(null, "3111"));
            },
            confirmModal: function () {
              return n
                .e("components/confirm-modal/confirm-modal")
                .then(n.bind(null, "4e5b"));
            },
          },
          c = function () {
            this.$createElement;
            this._self._c;
          },
          i = [];
      },
      d855: function (t, e, n) {
        "use strict";
        (function (t) {
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var o = n("b680"),
            c = {
              data: function () {
                return {
                  placeholder: "请输入会员协议...",
                  blogContent: "",
                  editorCtx: "",
                  num: "",
                  editorConter: "",
                  flag: !0,
                  status: "add",
                };
              },
              components: {
                confirmModal: function () {
                  n.e("components/confirm-modal/confirm-modal")
                    .then(
                      function () {
                        return resolve(n("4e5b"));
                      }.bind(null, n),
                    )
                    .catch(n.oe);
                },
              },
              onLoad: function (e) {
                var n = this,
                  c = this;
                (c.status = e.status),
                  (0, o.getuserProtocolSetting)().then(function (e) {
                    t.createSelectorQuery()
                      .select("#editor")
                      .context(function (t) {
                        t.context.setContents({ html: e.data }),
                          t.context.getContents({
                            success: function (t) {
                              c.num = t.text.length - 1;
                            },
                          }),
                          (n.editorCtx = t.context);
                      })
                      .exec();
                  });
              },
              methods: {
                undo: function () {
                  this.editorCtx.undo();
                },
                onInput: function (t) {
                  this.num = t.detail.text.length - 1;
                },
                onBlur: function (t) {
                  this.editorConter = t.detail.text;
                },
                submitBlog: function () {
                  this.flag = !1;
                  var e = this;
                  t.createSelectorQuery()
                    .select("#editor")
                    .context(function (n) {
                      n.context.setContents({ html: n.data }),
                        n.context.getContents({
                          success: function (n) {
                            (e.num = n.text.length - 1),
                              (e.blogContent = n.html),
                              (0, o.saveuserPtotocolSetting)({
                                textdata: e.blogContent,
                              }).then(function (e) {
                                var n = null;
                                200 == e.code
                                  ? ((n = "保存成功"),
                                    t.showToast({ icon: "none", title: n }),
                                    t.navigateBack({ delta: 1 }))
                                  : t.showToast({ icon: "none", title: e.msg });
                              });
                          },
                        });
                    })
                    .exec();
                },
                format: function (t) {
                  var e = t.target.dataset,
                    n = e.name,
                    o = e.value;
                  n && this.editorCtx.format(n, o);
                },
                headFlag: function () {
                  this.flag = !0;
                },
                headleCreal: function () {
                  (this.blogContent = ""),
                    (this.num = 0),
                    (this.editorConter = ""),
                    t
                      .createSelectorQuery()
                      .select("#editor")
                      .context(function (t) {
                        t.context.setContents({ html: "" });
                      })
                      .exec();
                },
                confirm: function () {
                  var e = this;
                  (this.blogContent = "<p><br></p>"),
                    (this.num = 0),
                    (this.editorConter = ""),
                    t
                      .createSelectorQuery()
                      .select("#editor")
                      .context(function (n) {
                        n.context.setContents({ html: "" }),
                          (0, o.saveuserPtotocolSetting)({
                            textdata: e.blogContent,
                          }).then(function (e) {
                            var n = null;
                            200 == e.code
                              ? ((n = "删除成功"),
                                t.navigateBack({ delta: 1 }),
                                t.showToast({ icon: "none", title: n }))
                              : t.showToast({ icon: "none", title: e.msg });
                          });
                      })
                      .exec();
                },
                headDelete: function () {
                  this.$refs.confirmModal.show = !0;
                },
              },
            };
          e.default = c;
        }).call(this, n("df3c").default);
      },
      daa1: function (t, e, n) {
        "use strict";
        var o = n("f882");
        n.n(o).a;
      },
      e22f: function (t, e, n) {
        "use strict";
        (function (t, e) {
          var o = n("47a9");
          n("86d2"), o(n("3240"));
          var c = o(n("75da"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(c.default);
        }).call(this, n("3223").default, n("df3c").createPage);
      },
      f882: function (t, e, n) {},
    },
    [["e22f", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
