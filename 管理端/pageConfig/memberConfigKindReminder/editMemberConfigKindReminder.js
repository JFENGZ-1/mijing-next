require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/memberConfigKindReminder/editMemberConfigKindReminder"],
    {
      "16d6": function (t, e, n) {
        "use strict";
        var o = n("c32b");
        n.n(o).a;
      },
      "3ff2": function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("b622"),
          i = n.n(o);
        for (var c in o)
          ["default"].indexOf(c) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return o[t];
              });
            })(c);
        e.default = i.a;
      },
      5877: function (t, e, n) {
        "use strict";
        (function (t, e) {
          var o = n("47a9");
          n("86d2"), o(n("3240"));
          var i = o(n("e79f"));
          (t.__webpack_require_UNI_MP_PLUGIN__ = n), e(i.default);
        }).call(this, n("3223").default, n("df3c").createPage);
      },
      b622: function (t, e, n) {
        "use strict";
        (function (t) {
          Object.defineProperty(e, "__esModule", { value: !0 }),
            (e.default = void 0);
          var o = n("7fc0"),
            i = {
              data: function () {
                return {
                  background: "#FFFFFF",
                  headtitle: "团课温馨提示",
                  title: "温馨提示",
                  placeholder: "如没有需要说明的，则保持为空即可",
                  blogContent: "",
                  editorCtx: "",
                  num: "",
                  editorConter: "",
                  flag: !0,
                  status: "add",
                  coursetype: 6,
                };
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
                navigation: function () {
                  n.e("components/navigation/index")
                    .then(
                      function () {
                        return resolve(n("af9e"));
                      }.bind(null, n),
                    )
                    .catch(n.oe);
                },
              },
              onLoad: function (e) {
                var n = this,
                  i = this;
                i.coursetype = e.coursetype;
                var c = {};
                (c.coursetype = i.coursetype),
                  7 == i.coursetype
                    ? (i.headtitle = "团课温馨提示")
                    : (i.headtitle = "私教温馨提示"),
                  (0, o.getwarmHint)(c).then(function (e) {
                    e.data &&
                      e.data.text &&
                      ((i.status = "edit"), (i.title = e.data.title)),
                      t
                        .createSelectorQuery()
                        .select("#editor")
                        .context(function (t) {
                          t.context.setContents({
                            html: e.data ? e.data.text : "",
                          }),
                            t.context.getContents({
                              success: function (t) {
                                i.num = t.text.length - 1;
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
                  var e = this,
                    n = this;
                  t.createSelectorQuery()
                    .select("#editor")
                    .context(function (i) {
                      i.context.setContents({ html: i.data }),
                        i.context.getContents({
                          success: function (i) {
                            (n.num = i.text.length - 1),
                              (n.blogContent = i.html),
                              n.blogContent && "<p><br></p>" != n.blogContent
                                ? e.num > 500
                                  ? t.showToast({
                                      icon: "none",
                                      title: "内容不能超过500字",
                                    })
                                  : (0, o.saveWarmHint)({
                                      title: n.title,
                                      coursetype: n.coursetype,
                                      text: n.blogContent,
                                    }).then(function (e) {
                                      var n = null;
                                      200 == e.code
                                        ? ((n = "保存成功"),
                                          t.showToast({
                                            icon: "none",
                                            title: n,
                                          }),
                                          setTimeout(function () {
                                            t.navigateBack({ delta: 1 });
                                          }, 1e3))
                                        : t.showToast({
                                            icon: "none",
                                            title: e.msg,
                                          });
                                    })
                                : t.showToast({
                                    icon: "none",
                                    title: "请填写内容！",
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
                          (0, o.saveWarmHint)({
                            title: "",
                            coursetype: e.coursetype,
                            text: "",
                          }).then(function (e) {
                            var n = null;
                            200 == e.code
                              ? ((n = "删除成功"),
                                t.showToast({ icon: "none", title: n }),
                                setTimeout(function () {
                                  t.navigateBack({ delta: 1 });
                                }, 1e3))
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
          e.default = i;
        }).call(this, n("df3c").default);
      },
      c32b: function (t, e, n) {},
      dff9: function (t, e, n) {
        "use strict";
        n.d(e, "b", function () {
          return i;
        }),
          n.d(e, "c", function () {
            return c;
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
          i = function () {
            this.$createElement;
            this._self._c;
          },
          c = [];
      },
      e79f: function (t, e, n) {
        "use strict";
        n.r(e);
        var o = n("dff9"),
          i = n("3ff2");
        for (var c in i)
          ["default"].indexOf(c) < 0 &&
            (function (t) {
              n.d(e, t, function () {
                return i[t];
              });
            })(c);
        n("16d6");
        var r = n("828b"),
          a = Object(r.a)(
            i.default,
            o.b,
            o.c,
            !1,
            null,
            "6b42867c",
            null,
            !1,
            o.a,
            void 0,
          );
        e.default = a.exports;
      },
    },
    [["5877", "common/runtime", "common/vendor", "pageConfig/common/vendor"]],
  ]);
