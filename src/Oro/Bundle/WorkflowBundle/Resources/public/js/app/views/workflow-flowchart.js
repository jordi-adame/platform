define(function (require) {
    var JsplubmAreaView = require('./jsplumb-area'),
        JsplubmBoxView = require('./jsplumb-box'),
        JsplubmTransitionView = require('./jsplumb-transition'),
        BaseCollectionView = require('oroui/js/app/views/base/collection-view'),
        WorkflowFlowchartView;

    WorkflowFlowchartView = JsplubmAreaView.extend({
        initialize: function () {
            WorkflowFlowchartView.__super__.initialize.apply(this, arguments);
        },

        render: function () {
            WorkflowFlowchartView.__super__.render.apply(this, arguments);

            var that = this,
                steps = this.model.get('steps'),
                stepCollectionView = new BaseCollectionView({
                    el: this.$el,
                    collection: steps,
                    animationDuration: 0,
                    // pass areaView to each model
                    itemView: function (options) {
                        options = _.extend({
                            areaView: that
                        }, options);
                        return new JsplubmBoxView(options);
                    },
                    autoRender: true
                }),
                transitionCollectionView = new BaseCollectionView({
                    el: this.$el,
                    collection: this.model.get('transitions'),
                    animationDuration: 0,
                    // pass areaView to each model
                    itemView: function (options) {
                        options = _.extend({
                            areaView: that,
                            stepCollection: steps,
                            stepCollectionView: stepCollectionView
                        }, options);
                        return new JsplubmTransitionView(options);
                    },
                    autoRender: true
                });
        }
    });

    return WorkflowFlowchartView;
});
